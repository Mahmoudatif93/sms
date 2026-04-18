<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GatewayResult extends Model
{
    use HasFactory;
    protected $table = 'gateway_result';
    protected $fillable = [
        'gateway_id',
        'value',
        'note',
        'type'
    ];
}
