<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Auth;

class Pipeline extends Model
{
    use HasFactory;

    protected $keyType = 'string'; // Ensure UUID is treated as a string
    public $incrementing = false;  // Prevent Laravel from treating it as an integer

    protected $fillable = ['id', 'name', 'description', 'status', 'assigned_to', 'color'];
    protected $casts = [
        'id' => 'string',  // Ensures it remains a string
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid(); //  Explicitly cast to string
            }
            if (empty($model->assigned_to)) {
                $model->assigned_to = Auth::id();
            }
            // Validate status against pipeline stages
            if (!empty($model->status)) {
                $validStatuses = PipelineStage::where('pipeline_id', $model->id)->pluck('name')->toArray();
                if (!in_array($model->status, $validStatuses)) {
                    $model->status = null; // Set status to null if not valid
                }
            }
        });

        static::updating(function ($model) {
            // Validate status against pipeline stages
            if (!empty($model->status)) {
                $validStatuses = PipelineStage::where('pipeline_id', $model->id)->pluck('name')->toArray();
                if (!in_array($model->status, $validStatuses)) {
                    $model->status = null; // Set status to null if not valid
                }
            }
        });
    }

    // Define relationship with User model
    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function deals()
    {
        return $this->hasMany(Deal::class, 'pipeline_id');
    }


    public function tabs()
    {
        return $this->hasMany(PipelineTab::class);
    }

    public function stages()
    {
        return $this->hasMany(PipelineStage::class);
    }
}
