<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class TicketActivityLog
 *
 * Represents activity logs for tickets.
 *
 * @package App\Models
 * @property string $id UUID Primary Key
 * @property string $ticket_id Foreign Key - The ticket this activity log belongs to
 * @property string|null $user_id Foreign Key - The user who performed the activity
 * @property string $activity_type The type of activity (e.g., created, updated, status_changed)
 * @property string $description A human-readable description of the activity
 * @property array|null $old_values The old values before the change (JSON)
 * @property array|null $new_values The new values after the change (JSON)
 * @property Carbon|null $created_at Timestamp when the activity log was created
 * @property Carbon|null $updated_at Timestamp when the activity log was last updated
 *
 * @property-read Ticket $ticket The ticket this activity log belongs to
 * @property-read User|null $user The user who performed the activity
 *
 * @method static Builder|TicketActivityLog newModelQuery()
 * @method static Builder|TicketActivityLog newQuery()
 * @method static Builder|TicketActivityLog query()
 * @method static Builder|TicketActivityLog whereId($value)
 * @method static Builder|TicketActivityLog whereTicketId($value)
 * @method static Builder|TicketActivityLog whereUserId($value)
 * @method static Builder|TicketActivityLog whereActivityType($value)
 *
 * @mixin Eloquent
 */
class TicketActivityLog extends Model
{
    use HasUuids;
    
    // Activity type constants
    const ACTIVITY_CREATED = 'ticket_created';
    const ACTIVITY_UPDATED = 'ticket_updated';
    const ACTIVITY_STATUS_CHANGED = 'status_changed';
    const ACTIVITY_PRIORITY_CHANGED = 'priority_changed';
    const ACTIVITY_ASSIGNED = 'agent_assigned';
    const ACTIVITY_UNASSIGNED = 'agent_unassigned';
    const ACTIVITY_MESSAGE_ADDED = 'message_added';
    const ACTIVITY_TAG_ADDED = 'tag_added';
    const ACTIVITY_TAG_REMOVED = 'tag_removed';
    const ACTIVITY_DUE_DATE_CHANGED = 'due_date_changed';
    const ACTIVITY_REOPENED = 'ticket_reopened';
    const ACTIVITY_CLOSED = 'ticket_closed';
    const ACTIVITY_RESOLVED = 'ticket_resolved';
    const ACTIVITY_MERGED = 'ticket_merged';
    const ACTIVITY_SPLIT = 'ticket_split';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ticket_activity_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'ticket_id',
        'user_id',
        'activity_type',
        'description',
        'old_values',
        'new_values',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * Get the ticket that this activity log belongs to.
     *
     * @return BelongsTo
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(TicketEntity::class, 'ticket_id');
    }

    /**
     * Get the user who performed the activity.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope a query to only include activity logs of a specific type.
     *
     * @param Builder $query
     * @param string $type
     * @return Builder
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('activity_type', $type);
    }

    /**
     * Scope a query to only include activity logs performed by a specific user.
     *
     * @param Builder $query
     * @param string $userId
     * @return Builder
     */
    public function scopeByUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include activity logs from a specific time period.
     *
     * @param Builder $query
     * @param Carbon $startDate
     * @param Carbon|null $endDate
     * @return Builder
     */
    public function scopeWithinPeriod(Builder $query, Carbon $startDate, ?Carbon $endDate = null): Builder
    {
        $query = $query->where('created_at', '>=', $startDate);
        
        if ($endDate) {
            $query = $query->where('created_at', '<=', $endDate);
        }
        
        return $query;
    }

    /**
     * Create a log entry for ticket creation.
     *
     * @param Ticket $ticket
     * @param User|null $user
     * @return TicketActivityLog
     */
    public static function logTicketCreation(TicketEntity $ticket, ?User $user = null): self
    {
        return self::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user ? $user->id : null,
            'activity_type' => self::ACTIVITY_CREATED,
            'description' => 'Ticket was created',
            'new_values' => [
                'subject' => $ticket->subject,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'source' => $ticket->source,
            ],
        ]);
    }

    /**
     * Create a log entry for ticket status change.
     *
     * @param Ticket $ticket
     * @param string $oldStatus
     * @param string $newStatus
     * @param User|null $user
     * @return TicketActivityLog
     */
    public static function logStatusChange(TicketEntity $ticket, string $oldStatus, string $newStatus, ?User $user = null): self
    {
        return self::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user ? $user->id : null,
            'activity_type' => self::ACTIVITY_STATUS_CHANGED,
            'description' => "Status changed from '{$oldStatus}' to '{$newStatus}'",
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => $newStatus],
        ]);
    }

    /**
     * Create a log entry for ticket assignment.
     *
     * @param Ticket $ticket
     * @param User|null $oldAgent
     * @param User $newAgent
     * @param User|null $assignedBy
     * @return TicketActivityLog
     */
    public static function logAssignment(TicketEntity $ticket, ?User $oldAgent, User $newAgent, ?User $assignedBy = null): self
    {
        return self::create([
            'ticket_id' => $ticket->id,
            'user_id' => $assignedBy ? $assignedBy->id : null,
            'activity_type' => self::ACTIVITY_ASSIGNED,
            'description' => "Ticket assigned to {$newAgent->name}",
            'old_values' => $oldAgent ? ['assigned_to' => $oldAgent->id, 'agent_name' => $oldAgent->name] : null,
            'new_values' => ['assigned_to' => $newAgent->id, 'agent_name' => $newAgent->name],
        ]);
    }
}