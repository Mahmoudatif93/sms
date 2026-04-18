<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConversionRequest extends Model
{
    use HasFactory;
    protected $fillable = ['user_id','wallet_transaction_id','balance_log_id','conversion_type','amount','points','status','handled_by','admin_notes'];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

}
