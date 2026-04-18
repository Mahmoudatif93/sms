<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganiztionTag extends Model
{
    protected $table = 'organization_tags'; // Replace with your actual table name
    protected $fillable = ['id','organization_id','tag_id'];


    public function tag()
    {
        return $this->belongsTo(Tag::class, 'tag_id');
    }
}
