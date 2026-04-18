<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_id',
        'bot_token',
        'chat_id',
        'enabled',
    ];

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }
}
