<?php
namespace App\Class\payment;
use App\Http\Interfaces\Payment\Service;
use App\Models\Service as MService;
use App\Enums\Service as EnumService;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Wallet;
use App\Models\BalanceUser;
use App\Models\User;
use App\Models\ConversionRequest;
use App\Models\OtherQuotaUser;
use App\Models\Organization;
use App\Models\OtherQuota;
use App\Traits\WalletManager;
class OtherService extends Service
{
   use WalletManager;

    public function getName(): string
    {
        return EnumService::SMS;
    }

    
    public function ChangeWallet($workspace, $amount, $points, $sms_price): bool  
    {
        
        $wallet = $this->getWorkspaceWallet($workspace, MService::where('name', EnumService::OTHER)->value('id'));
        
        if (!$wallet) {
            return false;
        }
        $response = \DB::transaction(function ()use ($wallet,$amount,$points,$sms_price) {
            try {
                if($this->changeBalance($wallet,$amount,EnumService::OTHER,"'Wallet recharge with ' . $amount . ' SAR'",0,null)){
                    return true;
                 }
                 throw new \Exception('Transaction failed');
            } catch (\Exception $e) {
                \Log::error('Wallet transaction failed: ' . $e->getMessage());
                return false;
            }
        });
        return $response;
    }

    public function ChangeWalletV2($object, $amount, $points, $sms_price): bool  {
        if (!($object instanceof Organization) && !($object instanceof Workspace)) {
            throw new \InvalidArgumentException('Object must be an Organization or Workspace instance');
        }
        $wallet = $this->getObjectWallet($object, MService::where('name', EnumService::OTHER)->value('id'));
        if (!$wallet) {
            return false;
        }
        $response = \DB::transaction(function ()use ($wallet,$amount,$points,$sms_price) {
            try {
                if($this->changeBalance($wallet,$amount,EnumService::OTHER,"Wallet funding: ".$amount." SAR deposited",0,null)){
                    return true;
                 }
                 throw new \Exception('Transaction failed');
            } catch (\Exception $e) {
                \Log::error('Wallet transaction failed: ' . $e->getMessage());
                return false;
            }
        });
        return $response;
    }
    
    public function addToWallet($amount, $points, Organization $organization)
    {
        $serviceId = MService::where('name', EnumService::OTHER)->value('id');
        $wallet = $organization->primaryWallet($serviceId);
        $wallet->amount += $amount;
        $wallet->save();
        return $wallet;
    }

    public function requestConversion(User $user, $amount)
    {
        $serviceId = MService::where('name', EnumService::OTHER)->first()->id;
        $wallet = Wallet::where('user_id', \Auth::id())->where('service_id', $serviceId)->first();
        if (!$wallet || $wallet->amount < $amount) {
            throw new \ErrorException('Insufficient funds or wallet not found');
        }
        $wallet->amount += -1 * $amount;
        $wallet->save();

        BalanceUser::updateOrCreate([
            'user_id' => \Auth::id(),
        ], [
            'balance' => \DB::raw('balance - ' . $amount),
            'currency' => 'SAR'
        ]);

        //TODO: get sms price from quta
        $points = ceil($amount / $user->getSmsPrice());
        //TODO:

        $quota = $this->getQuota($user, $amount, $amount);
        $wallet_transaction = $this->AddToWalletLog((-1 * $amount), $wallet->id, WalletTransactionType::USAGE, WalletTransactionStatus::PENDING, $quota, 'Convert ' . $amount . ' SAR to ' . $points . ' Points');
        ConversionRequest::create([
            'user_id' => \Auth::id(),
            'wallet_transaction_id' => $wallet_transaction->id,
            'conversion_type' => 'currency_to_points',
            'amount' => $amount,
            'points' => $points,
            'status' => \App\Enums\ConversionRequestStatus::PENDING
        ]);
        //TODO: send notifcation to admin ,

    }

    public function getQuota(User $user, $amount, $price)
    {

        //TODO: handel with menna
        $quota = OtherQuotaUser::where('user_id', $user->id)->latest()->first();
        if (!$quota) {
            $quota = OtherQuotaUser::insert([
                'user_id' => $user->id,
                'status' => 'active'
            ]);
        }
        return $quota;
    }

    public function setQuota($object, $amount, $sms_price)
    {
        if (!($object instanceof Organization) && !($object instanceof Workspace)) {
            throw new \InvalidArgumentException('Object must be an Organization or Workspace instance');
        }
        $quota = OtherQuota::where('quotable_type', get_class($object))
            ->where('quotable_id', $object->id)
            ->where('status', 'active')
            ->latest()
            ->first();
        if (!$quota) {
            $quota = $object->otherQuota()->create([
                'user_id' => $object->owner_id,
            ]);
        }
        return $quota;
    }

}
