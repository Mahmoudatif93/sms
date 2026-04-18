<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TaskFile extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'task_id',
        'file_path',
        'size'
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
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
