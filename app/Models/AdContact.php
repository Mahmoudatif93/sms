<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdContact extends Model
{
    use HasFactory;
    protected $table = 'ad_contact'; 
    protected $fillable = ['number'];

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'ad_contact_tag', 'ad_contact_id', 'tag_id');
    }
}
