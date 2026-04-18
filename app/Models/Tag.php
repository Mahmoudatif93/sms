<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasFactory;

    protected $table = 'tag';

    protected $fillable = ['name_ar', 'name_en', 'parent_id'];

    public function parent()
    {
        return $this->belongsTo(Tag::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Tag::class, 'parent_id');
    }

    public function organizations()
    {
        return $this->belongsToMany(Organization::class, 'organization_tags', 'tag_id', 'organization_id');
    }


    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_tags', 'board_tag_id', 'task_id');
    }
}
