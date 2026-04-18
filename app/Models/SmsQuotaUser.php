<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsQuotaUser extends Model
{
    use HasFactory;
    protected $fillable = ['user_id','status','sms_price','available_points'];

    public function walletTransactions()
    {
        return $this->morphMany(WalletTransaction::class, 'quotaable');
    }
}
