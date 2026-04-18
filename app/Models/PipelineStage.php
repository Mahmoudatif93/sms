<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PipelineStage extends Model
{
    use HasFactory;
    protected $keyType = 'string'; // Ensure UUID is treated as a string
    public $incrementing = false;  // Prevent Laravel from treating it as an integer

    protected $fillable = ['id', 'pipeline_id', 'name', 'position', 'color'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid(); //  Explicitly cast to string
            }
        });
    }
    public function pipeline()
    {
        return $this->belongsTo(Pipeline::class);
    }
}
