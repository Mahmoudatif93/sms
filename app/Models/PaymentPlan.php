<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentPlan extends Model
{
    use HasFactory;
    protected $table = 'payment_plan'; // Replace with your actual table name
    protected $fillable = [
        'user_id',
        'plan_id',
        'points_cnt',
        'payment_id', 'payment_bank_id',
    ];
}
