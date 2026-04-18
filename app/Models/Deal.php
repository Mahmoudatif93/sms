<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Auth;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use OSS\OssClient;
use OSS\Core\OssException;

class Deal extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;


    protected $keyType = 'string'; // Ensure UUID is treated as a string
    public $incrementing = false;  // Prevent Laravel from treating it as an integer


    protected $fillable = [
        'id',
        'title',
        'status',
        'due_date',
        'deal_type',
        'custom_fields',
        'workspace_id',
        'pipeline_id',
        'amount',
        'pipeline_stage_id',
        'position',
        'replace_files'
    ];

    protected $casts = [
        'id' => 'string', // Ensure ID is treated as a string
        //   'custom_fields' => 'array',
        'due_date' => 'datetime',
        'amount' => 'decimal:2'
    ];
    protected $appends = ['files_meta'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid(); // Explicitly cast to string
            }
            // Ensure status belongs to pipeline stages
            if (!empty($model->status) && !empty($model->pipeline_id)) {
                $validStages = PipelineStage::where('pipeline_id', $model->pipeline_id)->pluck('name')->toArray();
                if (!in_array($model->status, $validStages)) {
                    $model->status = null; // Set status to null if it's not a valid stage
                }
            }
        });
        static::updating(function ($model) {
            // Ensure status belongs to pipeline stages
            if (!empty($model->status) && !empty($model->pipeline_id)) {
                $validStages = PipelineStage::where('pipeline_id', $model->pipeline_id)->pluck('name')->toArray();
                if (!in_array($model->status, $validStages)) {
                    $model->status = null; // Set status to null if it's not a valid stage
                }
            }
        });
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class, 'pipeline_id');
    }



    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(ContactEntity::class, 'deal_contacts', 'deal_id', 'contact_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'deal_products', 'deal_id', 'product_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(DealFile::class, 'deal_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }
    public function reminders(): HasMany
    {
        return $this->hasMany(DealReminder::class, 'deal_id');
    }
    public function history(): HasMany
    {
        return $this->hasMany(DealHistory::class, 'deal_id');
    }
    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id');
    }


    public function getCustomFieldsAttribute($value)
    {
        // If NULL → return empty array
        if ($value === null) {
            return [];
        }

        // If already array
        if (is_array($value)) {
            return $value;
        }

        // If string "[]"
        if ($value === '[]') {
            return [];
        }

        // Decode JSON string safely
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        return [];
    }

    public function setCustomFieldsAttribute($value)
    {
        // Always store JSON string
        if ($value === null || $value === []) {
            $this->attributes['custom_fields'] = json_encode([]);
            return;
        }

        if (is_array($value)) {
            $this->attributes['custom_fields'] = json_encode($value);
            return;
        }

        $this->attributes['custom_fields'] = $value;
    }

    public function getFileUrl(string $collectionName, int $expirationInSeconds = 864000, string $disposition = 'inline'): ?string
    {
        $media = $this->getFirstMedia($collectionName);

        if (!$media) {
            return null;
        }

        $objectPath = $media->getPath();

        try {
            $fileUploadService = app()->make('App\Services\FileUploadService');
            return $fileUploadService->getSignUrl($objectPath, $expirationInSeconds, $disposition);
        } catch (\Exception $e) {
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
                continue;
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
