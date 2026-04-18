<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsQuota extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'sms_price',
        'available_points',
        'expire_date',
    ];

    public function quotable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function walletTransactions()
    {
        return $this->morphMany(WalletTransaction::class, 'quotaable');
    }

    // public function logs(): HasMany
    // {
    //     return $this->hasMany(SmsQuotaLog::class);
    // }
}
