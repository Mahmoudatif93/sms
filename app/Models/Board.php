<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Board extends Model
{
    use HasFactory;
    protected $keyType = 'string'; // Ensure UUID is treated as a string
    public $incrementing = false;  // Prevent Laravel from treating it as an integer


    protected $fillable = ['id', 'name', 'color', 'assigned_to'];

    protected $casts = [
        'id' => 'string', // Ensure ID is treated as a string

    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid(); // Explicitly cast to string
            }
        });
    }
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
    public function tabs()
    {
        return $this->hasMany(BoardTab::class);
    }

    public function stages()
    {
        return $this->hasMany(BoardStage::class, 'board_id');
    }
}
