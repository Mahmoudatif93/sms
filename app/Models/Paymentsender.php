<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Paymentsender extends Model
{
    use HasFactory;
    protected $table = 'payment_sender'; // Replace with your actual table name
    protected $fillable = [
        'user_id',
        'payment_bank_id',
        'sender_id',
        'payment_id','updated_at','created_at','walletable_id','walletable_type'
    ];

       // Polymorphic relationship for the Wallet
       public function walletable()
       {
           return $this->morphTo();
       }
}
