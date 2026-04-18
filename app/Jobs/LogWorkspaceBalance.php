<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\BalanceLog;
use App\Models\SmsQuota;
class LogWorkspaceBalance implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public $wallet;
    public $pointCnt;
    public $reason;
    public $sms_price;
    public $balanceExpireDate;
    public function __construct($wallet, $pointCnt, $reason, $sms_price=0,$balanceExpireDate=null)
    {
        $this->wallet = $wallet;
        $this->pointCnt = $pointCnt;
        $this->reason = $reason;
        $this->sms_price = $sms_price;  
        $this->balanceExpireDate = $balanceExpireDate;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->pointCnt >= 0) {
            $quota = $this->setQuota($this->wallet, $this->pointCnt, floatval($this->sms_price),$this->balanceExpireDate);
            $this->addPointToBalanceLog($this->wallet->user_id, $this->pointCnt, $this->price ?? 0, $this->reason, $this->balanceExpireDate,0,\App\Enums\BalanceLogStatus::ACTIVE,0);
        } else {
            $pointStillWallet = abs(num: $this->pointCnt);
            $quotas = $this->wallet->smsquotas()->where(function($q){
                $q->where('status', 'active')
                  ->where('available_points', '>', 0);
            })->get();

            $total_discount = 0;
            foreach ($quotas as $quota) {
                if ($quota->available_points > $pointStillWallet) {
                    $total_discount += $pointStillWallet * $quota->sms_price;
                    $quota->where('id', $quota->id)->update([
                        'available_points' => $quota->available_points - $pointStillWallet,
                    ]);
                    $this->addPointToBalanceLog($this->wallet->user_id, -1 * $pointStillWallet, $pointStillWallet * $quota->sms_price, $this->reason, null, 0, \App\Enums\BalanceLogStatus::ACTIVE,0);
                    break;

                } else {
                    $pointStillWallet = $pointStillWallet - $quota->available_points;
                    $total_discount += $quota->available_points * $quota->sms_price;

                    $quota->where('id', $quota->id)->update([
                        'available_points' => 0,
                        'status'=>'inactive',
                    ]);
                    $this->addPointToBalanceLog($this->wallet->user_id, -1 * $quota->available_points, $quota->available_points * $quota->sms_price, $this->reason, null, 0, \App\Enums\BalanceLogStatus::ACTIVE,0);
                }
  
            }
        }
    }

    protected function addPointToBalanceLog($user_id, $pointCnt, $price, $reason, $balanceExpireDate, $createdBy, $status, $quota_id)
    {
        BalanceLog::insert([
            'user_id' => $user_id,
            'points_cnt' => $pointCnt,
            'amount' => $price ?? 0,
            'reason' => $reason,
            'balance_expire_date' => $balanceExpireDate,
            'created_by' => $createdBy,
            "status" => $status,
            "quota_id" => $quota_id,
        ]);
    }

    protected function setQuota($wallet, $amount, $sms_price,$expire_date)
    {
        // $quota = SmsQuota::where('wallet_id', $wallet->id)
        //     ->where('status', 'active')
        //     ->latest()
        //     ->first();

        // if ($quota && $quota->sms_price == $sms_price && $quota->expire_date == $expire_date) {
        //     $quota->available_points = $quota->available_points + $amount;
        //     $quota->save();
        // } else {
        //     $quota = $wallet->smsquotas()->create([
        //         'user_id' => $wallet->user_id,
        //         'sms_price' => $sms_price,
        //         'available_points' => $amount,
        //         'expire_date' => $expire_date
        //     ]);
        // }
        $quota = $wallet->smsquotas()->create([
            'user_id' => $wallet->user_id,
            'sms_price' => $sms_price,
            'available_points' => $amount,
            'expire_date' => $expire_date
        ]);
        return $quota;
    }
}
