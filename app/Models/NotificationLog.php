<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Carbon\Carbon;

class NotificationLog extends Model
{

    /**
     * Indicates if the model's ID is auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the auto-incrementing ID.
     */
    protected $keyType = 'string';

    protected $fillable = [
        'notification_id',
        'type',
        'channel',
        'recipient_type',
        'recipient_id',
        'recipient_identifier',
        'title',
        'content',
        'data',
        'status',
        'external_id',
        'error_message',
        'retry_count',
        'next_retry_at',
        'sent_at',
        'delivered_at',
        'read_at',
        'user_id',
        'workspace_id',
        'organization_id',
        'template_id',
        'template_variables',
        'priority',
        'metadata',
        'scheduled_at',
    ];

    protected $casts = [
        'data' => 'array',
        'template_variables' => 'array',
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'scheduled_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_QUEUED = 'queued';
    const STATUS_SENDING = 'sending';
    const STATUS_SENT = 'sent';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_READ = 'read';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_SCHEDULED = 'scheduled';

    // Priority constants
    const PRIORITY_LOW = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    // Channel constants
    const CHANNEL_SMS = 'sms';
    const CHANNEL_EMAIL = 'email';
    const CHANNEL_TELEGRAM = 'telegram';
    const CHANNEL_PUSH = 'push';
    const CHANNEL_DATABASE = 'database';

    /**
     * Get the user that owns the notification
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the workspace that owns the notification
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the organization that owns the notification
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the template used for this notification
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class);
    }

    /**
     * Scope for pending notifications
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for failed notifications
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for sent notifications
     */
    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    /**
     * Scope for delivered notifications
     */
    public function scopeDelivered($query)
    {
        return $query->where('status', self::STATUS_DELIVERED);
    }

    /**
     * Scope for notifications that can be retried
     */
    public function scopeCanRetry($query)
    {
        return $query->where('status', self::STATUS_FAILED)
                    ->where('retry_count', '<', config('notifications.max_retries', 3))
                    ->where(function ($q) {
                        $q->whereNull('next_retry_at')
                          ->orWhere('next_retry_at', '<=', now());
                    });
    }

    /**
     * Scope for scheduled notifications ready to send
     */
    public function scopeReadyToSend($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
                    ->where('scheduled_at', '<=', now());
    }

    /**
     * Scope by channel
     */
    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by priority
     */
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Mark notification as sent
     */
    public function markAsSent(?string $externalId = null): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
            'external_id' => $externalId,
        ]);
    }

    /**
     * Mark notification as delivered
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'status' => self::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(): void
    {
        $this->update([
            'status' => self::STATUS_READ,
            'read_at' => now(),
        ]);
    }

    /**
     * Mark notification as failed
     */
    public function markAsFailed(string $error, bool $canRetry = true): void
    {
        $updates = [
            'status' => self::STATUS_FAILED,
            'error_message' => $error,
        ];

        if ($canRetry && $this->retry_count < config('notifications.max_retries', 3)) {
            $updates['retry_count'] = $this->retry_count + 1;
            $updates['next_retry_at'] = $this->calculateNextRetryTime();
        }

        $this->update($updates);
    }

    /**
     * Calculate next retry time based on exponential backoff
     */
    protected function calculateNextRetryTime(): Carbon
    {
        $baseDelay = config('notifications.retry_delay', 5); // minutes
        $delay = $baseDelay * pow(2, $this->retry_count); // exponential backoff

        return now()->addMinutes($delay);
    }

    /**
     * Check if notification can be retried
     */
    public function canRetry(): bool
    {
        return $this->status === self::STATUS_FAILED &&
               $this->retry_count < config('notifications.max_retries', 3) &&
               ($this->next_retry_at === null || $this->next_retry_at->isPast());
    }

    /**
     * Check if notification is successful
     */
    public function isSuccessful(): bool
    {
        return in_array($this->status, [
            self::STATUS_SENT,
            self::STATUS_DELIVERED,
            self::STATUS_READ,
        ]);
    }

    /**
     * Check if notification is pending
     */
    public function isPending(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_QUEUED,
            self::STATUS_SENDING,
            self::STATUS_SCHEDULED,
        ]);
    }

    /**
     * Get delivery time in seconds
     */
    public function getDeliveryTimeAttribute(): ?int
    {
        if ($this->sent_at && $this->delivered_at) {
            return $this->delivered_at->diffInSeconds($this->sent_at);
        }

        return null;
    }

    /**
     * Get read time in seconds from delivery
     */
    public function getReadTimeAttribute(): ?int
    {
        if ($this->delivered_at && $this->read_at) {
            return $this->read_at->diffInSeconds($this->delivered_at);
        }

        return null;
    }
}
