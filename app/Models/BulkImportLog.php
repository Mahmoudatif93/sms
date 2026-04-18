<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkImportLog extends Model
{
    use HasFactory;

    protected $table = 'bulk_import_logs';

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'organization_id',
        'user_id',
        'status',
        'total_records',
        'processed_records',
        'created_records',
        'invalid_records',
        'invalid_entries',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'invalid_entries' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_records' => 'integer',
        'processed_records' => 'integer',
        'created_records' => 'integer',
        'invalid_records' => 'integer',
    ];

    /**
     * Get the organization that owns the import log.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the user that created the import log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark the import as processing.
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark the import as completed.
     */
    public function markAsCompleted(int $createdCount, int $invalidCount, array $invalidEntries = []): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'processed_records' => $this->total_records,
            'created_records' => $createdCount,
            'invalid_records' => $invalidCount,
            'invalid_entries' => $invalidEntries,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark the import as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    /**
     * Update the progress of the import.
     */
    public function updateProgress(int $processedCount, int $createdCount = null, int $invalidCount = null): void
    {
        $updateData = [
            'processed_records' => $processedCount,
        ];

        if ($createdCount !== null) {
            $updateData['created_records'] = $createdCount;
        }

        if ($invalidCount !== null) {
            $updateData['invalid_records'] = $invalidCount;
        }

        $this->update($updateData);
    }

    /**
     * Get the progress percentage.
     */
    public function getProgressPercentage(): float
    {
        if ($this->total_records === 0) {
            return 0;
        }

        return round(($this->processed_records / $this->total_records) * 100, 2);
    }

    /**
     * Check if the import is in progress.
     */
    public function isInProgress(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    /**
     * Check if the import is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the import failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Get the duration of the import in seconds.
     */
    public function getDurationInSeconds(): ?float
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        return $this->completed_at->diffInSeconds($this->started_at);
    }

    /**
     * Get a human-readable status.
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            default => 'Unknown',
        };
    }
}
