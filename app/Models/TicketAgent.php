<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class TicketAgent
 *
 * Represents an agent assigned to a ticket, tracking assignment history.
 *
 * @package App\Models
 * @property int $id The primary key
 * @property string $ticket_id Foreign key - The ticket the agent is assigned to
 * @property int $inbox_agent_id Foreign key - The user who is assigned as an agent
 * @property int $assigned_at Unix timestamp when the agent was assigned
 * @property int|null $removed_at Unix timestamp when the agent was removed (null if still assigned)
 * @property int|null $created_at Unix timestamp when the record was created
 * @property int|null $updated_at Unix timestamp when the record was last updated
 * @property int|null $deleted_at Unix timestamp when the record was soft-deleted
 *
 * @property-read TicketEntity $ticket The ticket associated with this agent
 * @property-read User $agent The inbox agent assigned to this ticket
 *
 * @method static Builder|TicketAgent newModelQuery()
 * @method static Builder|TicketAgent newQuery()
 * @method static Builder|TicketAgent query()
 * @method static Builder|TicketAgent whereId($value)
 * @method static Builder|TicketAgent whereConversationId($value)
 * @method static Builder|TicketAgent whereInboxAgentId($value)
 * @method static Builder|TicketAgent whereAssignedAt($value)
 * @method static Builder|TicketAgent whereRemovedAt($value)
 * @method static Builder|TicketAgent whereCreatedAt($value)
 * @method static Builder|TicketAgent whereUpdatedAt($value)
 * @method static Builder|TicketAgent onlyTrashed()
 * @method static Builder|TicketAgent withTrashed()
 * @method static Builder|TicketAgent withoutTrashed()
 *
 * @mixin Eloquent
 */
class TicketAgent extends Pivot
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ticket_agents';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['ticket_id', 'inbox_agent_id', 'assigned_at', 'removed_at'];

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
     * Get the ticket associated with this assignment.
     *
     * @return BelongsTo
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(TicketEntity::class, 'ticket_id');
    }

    /**
     * Get the inbox agent assigned to the ticket.
     *
     * @return BelongsTo
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inbox_agent_id');
    }
}
