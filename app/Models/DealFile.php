<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DealFile extends Model
{
    use HasFactory;

    protected $fillable = ['deal_id', 'file_path', 'size'];
    protected $casts = [
        'deal_id' => 'string',  // Ensures it remains a string
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
    public function deal()
    {
        return $this->belongsTo(Deal::class, 'deal_id');
    }

    public function getFilePathAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            $fileUploadService = app()->make('App\Services\FileUploadService');
            return $fileUploadService->getSignUrl($value, 864000, 'inline');
        } catch (\Exception $e) {
            return $value; // لو فشل، يرجع المسار الأصلي
        }
    }
}
