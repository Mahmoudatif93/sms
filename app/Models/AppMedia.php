<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class AppMedia extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['user_identifier', 'media_id'];
}
