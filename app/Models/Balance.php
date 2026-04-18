<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Balance extends Model
{
    //TODO: delete it
    use HasFactory;
    protected $fillable = ['user_id', 'balance', 'currency', 'sms_points', 'other_services_balance'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
