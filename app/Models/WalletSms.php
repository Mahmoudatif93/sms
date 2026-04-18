<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletSms extends Model
{
    use HasFactory;

    protected $table = 'wallet'; // Replace with your actual table name
    protected $fillable = [
        'user_id', 'to_id',
        'payment_plan_id',
        'balance_log_id',
        'points_count', 'price', 'vat', 'gatway_sms_price', 'diler_sms_price',
         'date'
    ];
}
