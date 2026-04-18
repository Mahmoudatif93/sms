<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TaskRepeat extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'task_id',
        'is_recurring',
        'repeat_frequency',
        'repeat_interval',
        'repeat_days',
        'repeat_until'
    ];

    protected $casts = [
        'is_recurring' => 'boolean',
        'repeat_days' => 'array',
        'repeat_until' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
