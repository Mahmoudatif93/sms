<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class WhatsappStickerMessage extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'whatsapp_message_id',
        'media_id',
        'is_animated',
        'mime_type'
    ];

    public function whatsappMessage()
    {
        return $this->belongsTo(WhatsappMessage::class, 'whatsapp_message_id');
    }
}
