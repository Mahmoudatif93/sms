<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Favorit extends Model
{
    use HasFactory;

    protected $table = 'favorit'; // Replace with your actual table name
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'text',
        'workspace_id'
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user()
    {
        return $this->hasMany(User::class);
    }

    public static function get_by_user_id($user_id)
    {
        $results = Favorit::where('user_id', $user_id)->get();
        return $results;
    }

    public function scopeFilter($query, $filters)
    {
        return $query->when(!empty($filters['text']), function ($q) use ($filters) {
            $q->where('text', 'like', "%{$filters['text']}%");
        });
    }
}
