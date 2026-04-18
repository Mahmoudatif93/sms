<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramReactionMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'telegram_message_id',
        'emoji',
        'direction', // sent or received
    ];
}
