<?php

namespace App\Models;

use App\Enums\Workflow\ActionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * WhatsApp Workflow Action Model
 *
 * Represents an action to be executed as part of a workflow.
 *
 * @property string $id
 * @property string $whatsapp_workflow_id
 * @property string $action_type
 * @property array $action_config
 * @property int $order
 * @property bool $is_active
 * @property int $delay_seconds
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read WhatsappWorkflow $workflow
 * @property-read \Illuminate\Database\Eloquent\Collection|WhatsappWorkflowLog[] $logs
 */
class WhatsappWorkflowAction extends Model
{
    use HasUuids;

    protected $table = 'whatsapp_workflow_actions';

    protected $fillable = [
        'whatsapp_workflow_id',
        'action_type',
        'action_config',
        'order',
        'is_active',
        'delay_seconds',
    ];

    protected $casts = [
        'action_type' => ActionType::class,
        'action_config' => 'array',
        'is_active' => 'boolean',
        'order' => 'integer',
        'delay_seconds' => 'integer',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(WhatsappWorkflow::class, 'whatsapp_workflow_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WhatsappWorkflowLog::class, 'whatsapp_workflow_action_id');
    }

    /**
     * Get the template associated with this action (if action_type is send_template).
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessageTemplate::class, 'action_config->template_id');
    }

    /**
     * Get template data if this action sends a template.
     */
    public function getTemplateAttribute(): ?WhatsappMessageTemplate
    {
        if ($this->action_type !== ActionType::SEND_TEMPLATE) {
            return null;
        }

        $templateId = $this->getConfigValue('template_id');
        if (!$templateId) {
            return null;
        }

        return WhatsappMessageTemplate::find($templateId);
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order');
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Get a configuration value by key.
     */
    public function getConfigValue(string $key, $default = null)
    {
        return $this->action_config[$key] ?? $default;
    }

    /**
     * Check if this action sends a message.
     */
    public function sendsMessage(): bool
    {
        return $this->action_type->sendsMessage();
    }

    /**
     * Get available action types with labels.
     */
    public static function getAvailableActionTypes(): array
    {
        return ActionType::toSelectArray();
    }

    /**
     * Get the total delay for this action (including workflow delay).
     */
    public function getTotalDelay(): int
    {
        return $this->delay_seconds + ($this->workflow?->delay_seconds ?? 0);
    }
}

