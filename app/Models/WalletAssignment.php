<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletAssignment extends Model
{
    protected $fillable = [
        'wallet_id',
        'assignable_type',
        'assignable_id',
        'is_active'
    ];

    public function assignable()
    {
        return $this->morphTo();
    }

    public function wallet(){
        return $this->belongsTo(Wallet::class);
    }
}
