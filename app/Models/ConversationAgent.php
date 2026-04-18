<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class ConversationAgent
 *
 * Represents an agent assigned to a conversation, tracking assignment history.
 *
 * @package App\Models
 * @property int $id The primary key
 * @property string $conversation_id Foreign key - The conversation the agent is assigned to
 * @property int $inbox_agent_id Foreign key - The user who is assigned as an agent
 * @property int $assigned_at Unix timestamp when the agent was assigned
 * @property int|null $removed_at Unix timestamp when the agent was removed (null if still assigned)
 * @property int|null $created_at Unix timestamp when the record was created
 * @property int|null $updated_at Unix timestamp when the record was last updated
 * @property int|null $deleted_at Unix timestamp when the record was soft-deleted
 *
 * @property-read Conversation $conversation The conversation associated with this agent
 * @property-read User $agent The inbox agent assigned to this conversation
 *
 * @method static Builder|ConversationAgent newModelQuery()
 * @method static Builder|ConversationAgent newQuery()
 * @method static Builder|ConversationAgent query()
 * @method static Builder|ConversationAgent whereId($value)
 * @method static Builder|ConversationAgent whereConversationId($value)
 * @method static Builder|ConversationAgent whereInboxAgentId($value)
 * @method static Builder|ConversationAgent whereAssignedAt($value)
 * @method static Builder|ConversationAgent whereRemovedAt($value)
 * @method static Builder|ConversationAgent whereCreatedAt($value)
 * @method static Builder|ConversationAgent whereUpdatedAt($value)
 * @method static Builder|ConversationAgent onlyTrashed()
 * @method static Builder|ConversationAgent withTrashed()
 * @method static Builder|ConversationAgent withoutTrashed()
 *
 * @mixin Eloquent
 */
class ConversationAgent extends Pivot
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'conversation_agents';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['conversation_id', 'inbox_agent_id', 'assigned_at', 'removed_at'];

    /**
     * The attributes that should be cast to timestamps (Unix timestamps).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'assigned_at' => 'timestamp',
        'removed_at' => 'timestamp',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    /**
     * Get the conversation associated with this assignment.
     *
     * @return BelongsTo
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    /**
     * Get the inbox agent assigned to the conversation.
     *
     * @return BelongsTo
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inbox_agent_id');
    }
}
