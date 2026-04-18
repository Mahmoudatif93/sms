<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtherQuota extends Model
{
    protected $fillable = [
        'user_id',
        'status',
    ];

    public function quotable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function walletTransactions()
    {
        return $this->morphMany(WalletTransaction::class, 'quotaable');
    }
}
