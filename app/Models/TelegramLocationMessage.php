<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramLocationMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'latitude',
        'longitude',
        'name',
        'address',
    ];
}
