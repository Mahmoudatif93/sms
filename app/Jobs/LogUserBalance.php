<?php

namespace App\Jobs;

use App\Class\payment\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Service as MService;
use App\Models\BalanceLog;
use App\Enums\Service as EnumService;
use App\Models\SmsQuotaUser;
class LogUserBalance implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public $user;
    public $pointCnt;
    public $price;
    public $reason;
    public $balanceExpireDate;
    public $createdBy;
    public $status;
    public $quota_id;
    public function __construct($user, $pointCnt, $price, $reason, $balanceExpireDate, $createdBy, $status, $quota_id)
    {
        $this->user = $user;
        $this->pointCnt = $pointCnt;
        $this->price = $price;
        $this->reason = $reason;
        $this->balanceExpireDate = $balanceExpireDate;
        $this->createdBy = $createdBy;
        $this->status = $status;
        $this->quota_id = $quota_id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->pointCnt >= 0) {
            $this->addPointToBalanceLog($this->user->id, $this->pointCnt, $this->price ?? 0, $this->reason, $this->balanceExpireDate, $this->createdBy, $this->status, $this->quota_id);
        } else {
            $pointStillWallet = abs($this->pointCnt);
            $quotas = SmsQuotaUser::where('user_id', $this->user->id)->where('status', 'active')->orderBy('id', 'asc')->get();
            $service = new SmsService();
            $total_discount = 0;
            foreach ($quotas as $quota) {
                if ($quota->available_points > $pointStillWallet) {
                    $total_discount += $pointStillWallet * $quota->sms_price;
                    $quota->where('id', $quota->id)->update([
                        'available_points' => $quota->available_points - $pointStillWallet,
                    ]);
                    $this->addPointToBalanceLog($this->user->id, -1 * $pointStillWallet, $pointStillWallet * $quota->sms_price, $this->reason, $this->balanceExpireDate, $this->createdBy, $this->status,$quota->id);
                    break;

                } else {
                    // 5-4 = 1;
                    $pointStillWallet = $pointStillWallet - $quota->available_points;
                    $total_discount += $quota->available_points * $quota->sms_price;

                    $quota->where('id', $quota->id)->update([
                        'available_points' => 0,
                        'status'=>'inactive',
                    ]);
                    $this->addPointToBalanceLog($this->user->id, -1 * $quota->available_points, $quota->available_points * $quota->sms_price, $this->reason, $this->balanceExpireDate, $this->createdBy, $this->status,$quota->id);
                }
  
            }
            $service->addToWallet(-1 * ($total_discount), $this->pointCnt, $this->user);
        }

        if ($this->pointCnt < 0) {
            $logs = BalanceLog::where(['user_id' => $this->user->id, 'proccess_balance_expire_date' => 0])->where('balance_expire_date', '>=', now())
                ->orderBy('balance_expire_date', 'asc')
                ->orderBy('id', 'asc')
                ->get();
            if (count($logs) == 0) {
                //TODO: error send notification 
            }

            $pointStill = abs($this->pointCnt); //10
            foreach ($logs as $log) {
                if ($log->points_spent < $log->points_cnt) {
                    $availablePoints = $log->points_cnt - $log->points_spent; //10
                    if ($availablePoints > $pointStill) {
                        $log->where('id', $log->id)->update([
                            'points_spent' => ($log->points_spent + $pointStill),
                            'proccess_balance_expire_date' => ($availablePoints == $pointStill),
                        ]);
                        break;
                    } else {
                        $pointStill = $pointStill - $availablePoints;
                        $log->where('id', $log->id)->update([
                            'points_spent' => ($log->points_spent + $availablePoints),
                            'proccess_balance_expire_date' => 1,
                        ]);
                    }
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
}
