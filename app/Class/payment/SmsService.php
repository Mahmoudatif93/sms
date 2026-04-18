<?php
namespace App\Class\payment;

use App\Http\Interfaces\Payment\Service;
use App\Enums\Service as EnumService;
use App\Models\SmsQuotaUser;
use App\Models\User;
use App\Models\Organization;
use App\Models\Workspace;
use App\Models\SmsQuota;
use App\Models\Wallet;
use App\Models\Service as MService;
use App\Enums\BalanceLogStatus;
use App\Enums\WalletTransactionType;
use App\Enums\WalletTransactionStatus;
use App\Traits\WalletManager;
use Carbon\Carbon;

class SmsService extends Service
{
    use WalletManager;
    public function getName(): string
    {
        return EnumService::SMS;
    }

    public function ChangeWallet($workspace, $amount, $points, $sms_price): bool
    {
        $otherWallet = $this->getWorkspaceWallet($workspace, MService::where('name', EnumService::OTHER)->value('id'));
        $smsWallet = $this->getWorkspaceWallet($workspace, MService::where('name', EnumService::SMS)->value('id'));
        if (!$otherWallet || !$smsWallet) {
            return false;
        }
        $response = \DB::transaction(function ()use ($otherWallet,$smsWallet,$amount,$points,$sms_price) {
            try {
                if($this->changeBalance($otherWallet,$amount,EnumService::OTHER,"'Wallet recharge with ' . $amount . ' SAR'",0,null)){
                    if($this->changeBalance($smsWallet,$points,EnumService::SMS,"'Charge ' . $points . ' points with ' . $amount . ' SAR'",$sms_price,Carbon::now()->addYear())){
                         if($this->changeBalance($otherWallet,-1*$amount,EnumService::OTHER,"'Charge '. $points.' point by '. $amount.' SAR'",0,null)){
                             return true;
                         }
                     }
                 }
                 throw new \Exception('Transaction failed');
            } catch (\Exception $e) {
                \Log::error('Wallet transaction failed: ' . $e->getMessage());
                return false;
            }
        });
        return $response;
    }

    public function ChangeWalletV2($object, $amount, $points, $sms_price): bool
    {
        if (!($object instanceof Organization) && !($object instanceof Workspace)) {
            throw new \InvalidArgumentException('Object must be an Organization or Workspace instance');
        }

        $otherWallet = $this->getObjectWallet($object, MService::where('name', EnumService::OTHER)->value('id'));
        $smsWallet = $this->getObjectWallet($object, MService::where('name', EnumService::SMS)->value('id'));
        if (!$otherWallet || !$smsWallet) {
            return false;
        }
        $response = \DB::transaction(function ()use ($otherWallet,$smsWallet,$amount,$points,$sms_price) {
            try {
                if($this->changeBalance($otherWallet,$amount,EnumService::OTHER,"Wallet funding: ".$amount." SAR deposited",0,null)){
                    if($this->changeBalance($smsWallet,$points,EnumService::SMS,"Charge ". $points ." points with ".$amount." SAR'",$sms_price,Carbon::now()->addYear())){
                         if($this->changeBalance($otherWallet,-1*$amount,EnumService::OTHER,"Wallet debit: ".$amount." SAR for SMS points",0,null)){
                             return true;
                         }
                     }
                 }
                 throw new \Exception('Transaction failed');
            } catch (\Exception $e) {
                \Log::error('Wallet transaction failed: ' . $e->getMessage());
                return false;
            }
        });
        return $response;
    }

    public function addToWallet($amount, $sms_point, Organization $organization = null)
    {
        $serviceId = MService::where('name', EnumService::SMS)->value('id');
        $wallet = $organization->primaryWallet($serviceId);
        if (!$wallet) {
            //TODO: send Notification
            throw new \InvalidArgumentException('Object must be have wallet');
        }
        $wallet->amount += $amount;
        $wallet->sms_point += $sms_point;
        $wallet->save();
        return $wallet;

    }

    public function addPoints($points, $amount, $quota_id, $description, User $user = null, $balanceExpireDate = null)
    {
        $user == null ? auth('api')->user() : $user;
        $user->changeBalance($points, $description, Carbon::now()->addYear(), $createdBy = null, $amount, BalanceLogStatus::ACTIVE, $quota_id);
    }

    public function getOrganizationSmsPoints(Organization $organization, $amount)
    {
        $price_per_sms = $organization->getSmsPrice(); //TODO: get from user table or cota
        return ceil($amount / $price_per_sms);
    }


    public function requestConversion(User $user, $points)
    {
        // will not convert from point to currency
        /*
        $serviceId = MService::where('name', EnumService::SMS)->value('id');

        $wallet = Wallet::where('user_id', \Auth::id())->where('service_id', $serviceId)->first();
        if(!$wallet || $wallet->amount < $points){
            throw new \ErrorException('Insufficient funds or wallet not found');
        }
        $wallet->amount += -1*$points;
        $wallet->save();
        $amount = $points/.036; //  TODO get price sms from quta
        auth()->user()->changeBalance((-1)*$points, "Convert $points points to $amount SAR ", Carbon::now()->addYear(), $createdBy = null, $amount,BalanceLogStatus::PENDING);
        $wallet_transaction = $this->AddToWalletLog(-1 * $amount,$wallet->id,WalletTransactionType::CHARGE,"Convert $points points to $amount SAR ",WalletTransactionStatus::PENDING);
        ConversionRequest::create([
            'user_id'=> \Auth::id(),
            'wallet_transaction_id' =>  $wallet_transaction->id,
            'conversion_type' => 'points_to_currency',
            'amount' => $amount,
            'points' => $points,
            'status' => \App\Enums\ConversionRequestStatus::PENDING
        ]);
        //TODO: send notifcation to admin ,
            */
    }

    public function setQuota($object, $amount, $sms_price)
    {
        if (!($object instanceof Organization) && !($object instanceof Workspace)) {
            throw new \InvalidArgumentException('Object must be an Organization or Workspace instance');
        }
        $quota = SmsQuota::where('quotable_type', get_class($object))
            ->where('quotable_id', $object->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        if ($quota && $quota->sms_price == $sms_price) {
            $quota->available_points = $quota->available_points + $amount;
            $quota->save();
        } else {
            $quota = $object->smsQuota()->create([
                'user_id' => $object->owner_id,
                'sms_price' => $sms_price,
                'available_points' => $amount
            ]);
        }
        return $quota;
    }

    public function getQuota($object, $amount, $sms_price)
    {
        // if (!($object instanceof Organization) && !($object instanceof Workspace)) {
        //     throw new \InvalidArgumentException('Object must be an Organization or Workspace instance');
        // }
        // $quota = SmsQuota::where('quotable_type', get_class($object))
        // ->where('quotable_id', $object->id)
        // ->where('status', 'active')
        // ->latest()
        // ->first();
        // if (!$quota && $amount && $sms_price) {
        //     if ($amount < 0 || $sms_price < 0) {
        //         throw new \InvalidArgumentException('Amount and price must be positive values');
        //     }
        //     DB::beginTransaction();
        //     try {

        //     }catch (\Exception $e) {
        //         DB::rollBack();
        //         throw $e;
        //     }
        // }


        // $quota = $object->smsQuota()->create([
        // $quota = SmsQuotaUser::where('user_id', $user->id)->latest()->first();
        // if ($quota && $quota->sms_price == $sms_price) {
        //     $quota->available_points = $quota->available_points + $amount;
        //     $quota->save();
        // } else {
        //     $quota = SmsQuotaUser::create([
        //         'user_id' => $user->id,
        //         'status' => 'active',
        //         'sms_price' => $sms_price,
        //         'available_points' => $amount
        //     ]);
        // }
        // return $quota;
    }




    protected function UpdateWallet(User $user, $amount, $points, $sms_price, $desc)
    {
        $wallet = $this->addToWallet($amount, $points, $user);
        $quota = $this->getQuota($user, $points, $sms_price);
        $this->AddToWalletLog2($amount, $wallet->id, WalletTransactionType::CHARGE, WalletTransactionStatus::ACTIVE, $quota, "'Wallet recharge with ' . $amount . ' SAR'");
        //TODO: get every expired date
        $this->addPoints($points, $amount, $quota->id, $desc, $user);
        $this->AddToWalletLog2(-1 * $amount, $wallet->id, WalletTransactionType::CHARGE, WalletTransactionStatus::ACTIVE, $quota, "'Charge '. $points.' point by '. $amount.' SAR'");
    }
}
