<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use OSS\OssClient;
use OSS\Core\OssException;

class Task extends Model  implements HasMedia
{

    use HasFactory, InteractsWithMedia;


    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'id',
        'name',
        'board_id',
        'board_stage_id',
        'priority',
        'position',
        'start_date',
        'due_date',
        'custom_fields',
        'description',
        'parent_task_id',
        'replace_files'
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'start_date' => 'datetime',
        'due_date' => 'datetime',
    ];
    protected $appends = ['files_meta'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function boardStage(): HasOne
    {
        return $this->hasOne(BoardStage::class, 'id', 'board_stage_id');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(TaskChecklist::class);
    }

    public function parentTask(): HasOne
    {
        return $this->hasOne(Task::class, 'id', 'parent_task_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(TaskLink::class)->with('linkedEntity');
    }

    public function files(): HasMany
    {
        return $this->hasMany(TaskFile::class);
    }



    public function history(): HasMany
    {
        return $this->hasMany(TaskHistory::class);
    }


    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BoardTag::class, 'task_tags', 'task_id', 'board_tag_id');
    }
    public function repeatSettings()
    {
        return $this->hasOne(TaskRepeat::class, 'task_id');
    }

    public function reminders()
    {
        return $this->hasMany(TaskReminder::class, 'task_id');
    }

    public function watchers()
    {
        return $this->hasMany(TaskWatcher::class, 'task_id');
    }

    public function observers()
    {
        return $this->hasMany(TaskObserver::class, 'task_id');
    }

    public function getFileUrl(string $collectionName, int $expirationInSeconds = 864000, string $disposition = 'inline'): ?string
    {
        $media = $this->getFirstMedia($collectionName);

        if (!$media) {
            return null; // No media found
        }

        $objectPath = $media->getPath();

        try {
            $fileUploadService = app()->make('App\Services\FileUploadService');
            return $fileUploadService->getSignUrl($objectPath, $expirationInSeconds, $disposition);
        } catch (\Exception $e) {
            // Handle exception if needed (log, etc.)
            return null;
        }
    }

    public function getFilesUrls(string $collectionName, int $expirationInSeconds = 864000, string $disposition = 'inline'): array
    {
        $mediaItems = $this->getMedia($collectionName);

        $urls = [];
        foreach ($mediaItems as $media) {
            try {
                $objectPath = $media->getPath();
                $fileUploadService = app()->make('App\Services\FileUploadService');
                $urls[] = $fileUploadService->getSignUrl($objectPath, $expirationInSeconds, $disposition);
            } catch (\Exception $e) {
                continue; // Skip if one file fails
            }
        }

        return $urls;
    }
    public function getFilesMetaAttribute(): array
    {
        return $this->files->map(function ($file) {

            $path = parse_url($file->file_path, PHP_URL_PATH);
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            // Convert extension to MIME type
            $mimeType = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png'         => 'image/png',
                'gif'         => 'image/gif',
                'webp'        => 'image/webp',
                'svg'         => 'image/svg+xml',
                'pdf'         => 'application/pdf',
                'doc'         => 'application/msword',
                'docx'        => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls'         => 'application/vnd.ms-excel',
                'xlsx'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'csv'         => 'text/csv',
                'txt'         => 'text/plain',
                default       => 'application/octet-stream',
            };

            return [
                'id'   => $file->id,
                'name' => basename($path),
                'type' => $mimeType,
                'size' => $file->size,
                'url'  => $file->file_path,
            ];
        })->values()->toArray();
    }


    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
