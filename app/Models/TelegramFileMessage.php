<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramFileMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', // image, video, audio, document
        'caption',
        'file_id',
        'file_path',
    ];
}
