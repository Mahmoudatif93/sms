<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BalanceLog extends Model
{
    use HasFactory;

    protected $table = 'balance_log'; // Replace with your actual table name
    public $timestamps = false;

    protected $fillable = [
        'id', 'user_id', 'points_cnt',
        'amount', 'reason','balance_expire_date','proccess_balance_expire_date','points_spent','charge_request_bank_id'
        ,'created_by','date'
    ];

    public function User()
    {
        return $this->hasMany(User::class, 'id', 'user_id');
    }
}
