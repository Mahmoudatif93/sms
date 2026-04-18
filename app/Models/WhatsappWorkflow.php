<?php

namespace App\Models;

use App\Enums\Workflow\TriggerType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * WhatsApp Workflow Model
 *
 * Unified workflow model that handles all types of workflow triggers.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $name
 * @property string|null $description
 * @property string $trigger_type
 * @property array $trigger_config
 * @property bool $is_active
 * @property int $priority
 * @property int $delay_seconds
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read Workspace $workspace
 * @property-read \Illuminate\Database\Eloquent\Collection|WhatsappWorkflowAction[] $actions
 * @property-read \Illuminate\Database\Eloquent\Collection|WhatsappWorkflowLog[] $logs
 */
class WhatsappWorkflow extends Model
{
    use HasUuids;

    protected $table = 'whatsapp_workflows';

    protected $fillable = [
        'workspace_id',
        'flow_id',
        'flow_name',
        'flow_description',
        'name',
        'description',
        'trigger_type',
        'trigger_config',
        'is_active',
        'priority',
        'delay_seconds',
    ];

    protected $casts = [
        'trigger_type' => TriggerType::class,
        'trigger_config' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'delay_seconds' => 'integer',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(WhatsappWorkflowAction::class)->orderBy('order');
    }

    public function activeActions(): HasMany
    {
        return $this->hasMany(WhatsappWorkflowAction::class)
            ->where('is_active', true)
            ->orderBy('order');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WhatsappWorkflowLog::class);
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForWorkspace(Builder $query, string $workspaceId): Builder
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopeForTriggerType(Builder $query, TriggerType $type): Builder
    {
        return $query->where('trigger_type', $type->value);
    }

    // =========================================================================
    // Query Methods
    // =========================================================================

    /**
     * Get active workflows for a template status trigger.
     */
    public static function getForTemplateStatus(int $templateId, string $status): \Illuminate\Database\Eloquent\Collection
    {
        return static::query()
            ->active()
            ->forTriggerType(TriggerType::TEMPLATE_STATUS)
            ->whereJsonContains('trigger_config->template_id', "$templateId")
            ->whereJsonContains('trigger_config->status', $status)
            ->orderByDesc('priority')
            ->with('activeActions')
            ->get();
    }

    /**
     * Get active workflow for an interactive reply.
     */
    public static function getForInteractiveReply(
        int $draftId,
        TriggerType $triggerType,
        string $replyId,
        string $workspaceId
    ): ?self {
        $configKey = $triggerType === TriggerType::BUTTON_REPLY ? 'button_id' : 'row_id';

        return static::query()
        ->where('workspace_id',$workspaceId)
            ->active()
            ->forTriggerType($triggerType)
            ->whereJsonContains('trigger_config->interactive_draft_id', $draftId)
            ->whereJsonContains("trigger_config->{$configKey}", $replyId)
            ->orderByDesc('priority')
            ->with('activeActions')
            ->first();
    }

    public static function processConversationStarted(string $conversationId,$workspaceId): \Illuminate\Database\Eloquent\Collection
    {
        return static::query()
            ->active()
            ->forTriggerType(TriggerType::START_CONVERSATION)
            ->where('workspace_id',$workspaceId)
            // ->whereJsonContains('trigger_config->conversation_id', $conversationId)
            ->orderByDesc('priority')
            ->with('activeActions')
            ->get();
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    public function getTriggerConfigValue(string $key, $default = null)
    {
        return $this->trigger_config[$key] ?? $default;
    }

    public function isTemplateWorkflow(): bool
    {
        return $this->trigger_type === TriggerType::TEMPLATE_STATUS;
    }

    public function isInteractiveWorkflow(): bool
    {
        return $this->trigger_type->isInteractive();
    }
}

