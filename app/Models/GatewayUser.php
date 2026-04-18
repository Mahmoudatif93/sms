<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GatewayUser extends Model
{
    use HasFactory;
    protected $table = 'gateway_sender';
    protected $fillable = [
        'gateway_id',
        'user_id',
    ];
}
