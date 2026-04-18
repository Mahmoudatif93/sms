<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramMessageStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'telegram_message_id',
        'status', // sent, delivered, read
        'timestamp',
    ];
}
