<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * WhatsApp Workflow Log Model
 *
 * Records the execution history of workflow actions.
 *
 * @property string $id
 * @property string $whatsapp_workflow_id
 * @property string|null $whatsapp_workflow_action_id
 * @property string|null $trigger_message_id
 * @property string $status
 * @property array|null $context
 * @property array|null $result
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read WhatsappWorkflow $workflow
 * @property-read WhatsappWorkflowAction|null $action
 * @property-read WhatsappMessage|null $triggerMessage
 */
class WhatsappWorkflowLog extends Model
{
    use HasUuids;

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $table = 'whatsapp_workflow_logs';

    protected $fillable = [
        'whatsapp_workflow_id',
        'whatsapp_workflow_action_id',
        'trigger_message_id',
        'status',
        'context',
        'result',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'context' => 'array',
        'result' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(WhatsappWorkflow::class, 'whatsapp_workflow_id');
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(WhatsappWorkflowAction::class, 'whatsapp_workflow_action_id');
    }

    public function triggerMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessage::class, 'trigger_message_id', 'id');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    // =========================================================================
    // Status Management
    // =========================================================================

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(array $result = []): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'result' => $result,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function getDuration(): ?int
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }
        return $this->completed_at->diffInSeconds($this->started_at);
    }
}

