<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BoardStage extends Model
{
    use HasFactory;

    protected $keyType = 'string'; // UUID primary key
    public $incrementing = false;

    protected $fillable = ['id', 'board_id', 'name', 'color', 'position'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function board()
    {
        return $this->belongsTo(Board::class, 'board_id');
    }
}
