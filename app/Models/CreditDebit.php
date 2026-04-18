<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditDebit extends Model
{
    use HasFactory;

    protected $table = 'credit_debit'; // Replace with your actual table name

    protected $fillable = [
        'user_id',
        'amount',
        'note',
        'date',
    ];

    // Define a relationship to the User model (if applicable)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
