<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Class Ticket
 *
 * Represents a customer support ticket.
 *
 * @package App\Models
 * @property string $id UUID Primary Key
 * @property string $ticket_number Unique ticket identifier number
 * @property string $workspace_id Foreign Key - The workspace the ticket belongs to
 * @property string $subject The subject/title of the ticket
 * @property string|null $description The detailed description of the ticket
 * @property string $status The status of the ticket (e.g., open, in_progress, resolved, closed)
 * @property string $language The status of the ticket (e.g., ar, en)
 * @property string $priority The priority of the ticket (e.g., low, medium, high, urgent)
 * @property string $source Where the ticket originated from (conversation, email, iframe)
 * @property string|null $contact_id Foreign Key - The contact associated with the ticket
 * @property string|null $channel_id Foreign Key - The channel associated with the ticket
 * @property string|null $conversation_id Foreign Key - The conversation that was converted to this ticket
 * @property string|null $email The email address associated with the ticket (for email-sourced tickets)
 * @property string|null $assigned_to Foreign Key - The user assigned to handle this ticket
 * @property Carbon|null $due_date The deadline for resolving this ticket
 * @property Carbon|null $created_at Timestamp when the ticket was created
 * @property Carbon|null $updated_at Timestamp when the ticket was last updated
 * @property Carbon|null $deleted_at Timestamp when the ticket was soft-deleted (null if not deleted)
 *
 * @property-read Workspace $workspace The workspace associated with this ticket
 * @property-read ContactEntity|null $contact The contact associated with this ticket
 * @property-read Channel|null $channel The channel associated with this ticket
 * @property-read Conversation|null $conversation The conversation associated with this ticket
 * @property-read User|null $assignedAgent The agent assigned to this ticket
 * @property-read \Illuminate\Database\Eloquent\Collection|TicketMessage[] $messages The messages associated with this ticket
 * @property-read \Illuminate\Database\Eloquent\Collection|TicketTag[] $tags The tags associated with this ticket
 * @property-read \Illuminate\Database\Eloquent\Collection|TicketActivityLog[] $activityLogs The activity logs associated with this ticket
 *
 * @method static Builder|Ticket newModelQuery()
 * @method static Builder|Ticket newQuery()
 * @method static Builder|Ticket query()
 * @method static Builder|Ticket whereId($value)
 * @method static Builder|Ticket whereWorkspaceId($value)
 * @method static Builder|Ticket whereStatus($value)
 * @method static Builder|Ticket wherePriority($value)
 * @method static Builder|Ticket whereSource($value)
 * @method static Builder|Ticket whereAssignedTo($value)
 * @method static Builder|Ticket onlyTrashed()
 * @method static Builder|Ticket withTrashed()
 * @method static Builder|Ticket withoutTrashed()
 *
 * @mixin Eloquent
 */
class TicketEntity extends Model
{
    use HasUuids, SoftDeletes;
    
    // Status constants
    const STATUS_OPEN = 'open';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_CLOSED = 'closed';
    const STATUS_SPAM = 'spam';
    
    // Priority constants
    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';
    
    // Source constants
    const SOURCE_CONVERSATION = 'conversation';
    const SOURCE_EMAIL = 'email';
    const SOURCE_IFRAME = 'iframe';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tickets';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'subject',
        'description',
        'status',
        'language',
        'priority',
        'source',
        'workspace_id',
        'contact_id',
        'channel_id',
        'conversation_id',
        'email',
        'assigned_to',
        'due_date'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'due_date' => 'datetime',
    ];

    /**
     * Boot the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            // Generate a unique ticket number if not already set
            if (empty($ticket->ticket_number)) {
                $ticket->ticket_number = self::generateTicketNumber();
            }
        });
    }

    /**
     * Generate a unique ticket number.
     *
     * @return string
     */
    public static function generateTicketNumber()
    {
        $prefix = 'TKT-';
        $random = strtoupper(Str::random(5));
        $timestamp = time();
        
        $ticketNumber = $prefix . $random . '-' . substr($timestamp, -4);
        
        // Ensure the ticket number is unique
        while (self::where('ticket_number', $ticketNumber)->exists()) {
            $random = strtoupper(Str::random(5));
            $timestamp = time();
            $ticketNumber = $prefix . $random . '-' . substr($timestamp, -4);
        }
        
        return $ticketNumber;
    }

    /**
     * Get the workspace associated with the ticket.
     *
     * @return BelongsTo
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    /**
     * Get the contact associated with the ticket.
     *
     * @return BelongsTo
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(ContactEntity::class, 'contact_id');
    }

    /**
     * Get the channel associated with the ticket.
     *
     * @return BelongsTo
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    /**
     * Get the conversation associated with the ticket.
     *
     * @return BelongsTo
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    /**
     * Get the agent assigned to the ticket.
     *
     * @return BelongsTo
     */
    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the messages associated with the ticket.
     *
     * @return HasMany
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class, 'ticket_id');
    }

    /**
     * Get the tags associated with the ticket.
     *
     * @return BelongsToMany
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(TicketTag::class, 'ticket_tag_pivot', 'ticket_id', 'tag_id');
    }

    /**
     * Get the activity logs associated with the ticket.
     *
     * @return HasMany
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(TicketActivityLog::class, 'ticket_id');
    }

    /**
     * Get all tickets that are overdue.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->whereNotIn('status', [self::STATUS_RESOLVED, self::STATUS_CLOSED]);
    }

    /**
     * Get tickets assigned to a specific user.
     *
     * @param Builder $query
     * @param string $userId
     * @return Builder
     */
    public function scopeAssignedTo(Builder $query, string $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Get unassigned tickets.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_to');
    }

    /**
     * Get tickets by status.
     *
     * @param Builder $query
     * @param string $status
     * @return Builder
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Get tickets by priority.
     *
     * @param Builder $query
     * @param string $priority
     * @return Builder
     */
    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    /**
     * Get tickets by source.
     *
     * @param Builder $query
     * @param string $source
     * @return Builder
     */
    public function scopeBySource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    /**
     * Get the agents associated with the ticket.
     *
     * @return BelongsToMany
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_agents', 'ticket_id', 'inbox_agent_id')
            ->withPivot('assigned_at', 'removed_at')
            ->withTimestamps()
            ->withTrashed();
    }


    /**
     * Get the current agent assigned to the ticket.
     *
     *
     */
    public function currentAgent()
    {
        return $this->agents()
            ->wherePivotNull('removed_at')
            ->latest('pivot_assigned_at')
            ->first();
    }

}