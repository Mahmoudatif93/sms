<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StatisticsProcessing extends Model
{
    use HasFactory;

    protected $table = 'statistics_processing';

    protected $fillable = [
        'processing_id',
        'user_id',
        'workspace_id',
        'channel_id',
        'all_numbers',
        'sender_name',
        'message',
        'send_time_method',
        'send_time',
        'sms_type',
        'repeation_times',
        'excel_file',
        'message_length',
        'status',
        'total_numbers',
        'processed_numbers',
        'total_cost',
        'entries_json',
        'all_numbers_json',
        'error_message',
        'started_at',
        'completed_at',
        'approved_at',
        'approved_by'
    ];

    protected $casts = [
        'send_time' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'approved_at' => 'datetime',
        'total_cost' => 'decimal:4',
        'entries_json' => 'array',
        'all_numbers_json' => 'array'
    ];

    // Status constants
    const STATUS_PENDING = 'PENDING';
    const STATUS_PROCESSING = 'PROCESSING';
    const STATUS_COMPLETED = 'COMPLETED';
    const STATUS_FAILED = 'FAILED';
    const STATUS_APPROVED = 'APPROVED';
    const STATUS_REJECTED = 'REJECTED';

    /**
     * Generate a unique processing ID
     */
    public static function generateProcessingId(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Get processing progress percentage
     */
    public function getProgressPercentage(): float
    {
        if ($this->total_numbers == 0) {
            return 0;
        }
        
        return round(($this->processed_numbers / $this->total_numbers) * 100, 2);
    }

    /**
     * Check if processing is complete
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if processing failed
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if processing is in progress
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Check if processing is approved
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if processing is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Mark processing as started
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now()
        ]);
    }

    /**
     * Mark processing as completed
     */
    public function markAsCompleted(array $entries, array $allNumbers, float $totalCost): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'entries_json' => $entries,
            'all_numbers_json' => $allNumbers,
            'total_cost' => $totalCost,
            'processed_numbers' => count($allNumbers)
        ]);
    }

    /**
     * Mark processing as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'completed_at' => now(),
            'error_message' => $errorMessage
        ]);
    }

    /**
     * Mark processing as rejected due to insufficient balance
     */
    public function markAsRejected(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'completed_at' => now(),
            'error_message' => $errorMessage
        ]);
    }

    /**
     * Approve the processing
     */
    public function approve(int $approvedBy): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $approvedBy
        ]);
    }

    /**
     * Check if processing was auto-approved by system
     */
    public function isAutoApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED && $this->approved_by === 0;
    }

    /**
     * Check if processing was manually approved by user
     */
    public function isManuallyApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED && $this->approved_by > 0;
    }

    /**
     * Reject the processing
     */
    public function reject(int $rejectedBy): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'approved_at' => now(),
            'approved_by' => $rejectedBy
        ]);
    }

    /**
     * Update processing progress
     */
    public function updateProgress(int $processedNumbers): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'processed_numbers' => $processedNumbers
        ]);
    }

    /**
     * Get user relationship
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get workspace relationship
     */
    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Convert to Outbox/Message format for direct creation
     */
    public function toMessageData(): array
    {
        return [
            'channel' => 'DIRECT',
            'user_id' => $this->user_id,
            'workspace_id' => $this->workspace_id,
            'text' => $this->message,
            'count' => $this->processed_numbers,
            'cost' => $this->total_cost,
            'length' => $this->message_length,
            'creation_datetime' => \Carbon\Carbon::now(),
            'sending_datetime' => $this->send_time,
            'repeation_period' => 0,
            'repeation_times' => $this->repeation_times ?? 0,
            'variables_message' => $this->sms_type == "VARIABLES" ? 1 : 0,
            'sender_name' => $this->sender_name,
            'excel_file_numbers' => $this->excel_file,
            'all_numbers' => json_encode($this->all_numbers_json),
            'encrypted' => $this->sms_type == "ADS" ? 1 : 0,
            'auth_code' => randomAuthCode(),
            'advertising' => $this->shouldReview() ? 1 : 0,
            'sent_cnt' => 0,
            'lang' => \App\Helpers\Sms\MessageHelper::calcMessageLang($this->message),
        ];
    }

    /**
     * Check if message should be reviewed
     */
    public function shouldReview(): bool
    {
        return $this->sms_type === 'ADS';
    }

    /**
     * Convert to MessageStatistic format for compatibility (deprecated)
     * @deprecated Use toMessageData() instead
     */
    public function toMessageStatistic(): array
    {
        return [
            'user_id' => $this->user_id,
            'workspace_id' => $this->workspace_id,
            'all_numbers' => $this->all_numbers,
            'all_numbers_json' => json_encode($this->all_numbers_json),
            'sender_name' => $this->sender_name,
            'message' => $this->message,
            'send_time_method' => $this->send_time_method,
            'send_time' => $this->send_time,
            'sms_type' => $this->sms_type,
            'repeation_times' => $this->repeation_times,
            'excle_file' => $this->excel_file,
            'leng' => $this->message_length,
            'cost' => $this->total_cost,
            'count' => $this->processed_numbers
        ];
    }
}
