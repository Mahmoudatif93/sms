<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtherQuotaUser extends Model
{
    use HasFactory;

    protected $fillable = ['user_id','status'];

    public function walletTransactions()
    {
        return $this->morphMany(WalletTransaction::class, 'quotaable');
    }
}
