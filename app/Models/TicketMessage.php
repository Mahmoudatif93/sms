<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Class TicketMessage
 *
 * Represents a message within a ticket.
 *
 * @package App\Models
 * @property string $id UUID Primary Key
 * @property string $ticket_id Foreign Key - The ticket this message belongs to
 * @property string $sender_type The type of the sender (e.g., App\Models\User, App\Models\ContactEntity)
 * @property string $sender_id The ID of the sender
 * @property string $content The message content 
 * @property string $message_type The message message type (e.g., 'message', 'private_note', 'activity_log')
 * @property bool $is_private Whether this message is only visible to agents
 * @property Carbon|null $created_at Timestamp when the message was created
 * @property Carbon|null $updated_at Timestamp when the message was last updated
 * @property Carbon|null $deleted_at Timestamp when the message was soft-deleted (null if not deleted)
 *
 * @property-read Ticket $ticket The ticket this message belongs to
 * @property-read Model|Eloquent $sender The sender of this message
 * @property-read \Illuminate\Database\Eloquent\Collection|TicketAttachment[] $attachments The attachments for this message
 *
 * @method static Builder|TicketMessage newModelQuery()
 * @method static Builder|TicketMessage newQuery()
 * @method static Builder|TicketMessage query()
 * @method static Builder|TicketMessage whereId($value)
 * @method static Builder|TicketMessage whereTicketId($value)
 * @method static Builder|TicketMessage whereIsPrivate($value)
 * @method static Builder|TicketMessage onlyTrashed()
 * @method static Builder|TicketMessage withTrashed()
 * @method static Builder|TicketMessage withoutTrashed()
 *
 * @mixin Eloquent
 */
class TicketMessage extends Model
{
    use HasUuids, SoftDeletes;

    const MESSAGE_TYPE_MESSAGE = 'message';
    const MESSAGE_TYPES = [
        self::MESSAGE_TYPE_MESSAGE,
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ticket_messages';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'ticket_id',
        'sender_type',
        'sender_id',
        'content',              // Keep this if you want to maintain backward compatibility
        'message_type', // Add this field: 'message', 'private_note', 'activity_log'
        'messageable_type',
        'messageable_id',
        'is_private',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_private' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the ticket that this message belongs to.
     *
     * @return BelongsTo
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(TicketEntity::class, 'ticket_id');
    }

    /**
     * Get the sender of this message.
     *
     * @return MorphTo
     */
    public function sender(): MorphTo
    {
        return $this->morphTo();
    }

     /**
     * Get the messageable entity associated with this message.
     *
     * @return MorphTo
     */
    public function messageable(): MorphTo
    {
        return $this->morphTo();
    }
    
    // /**
    //  * Get the attachments for this message.
    //  *
    //  * @return HasMany
    //  */
    // public function attachments(): HasMany
    // {
    //     return $this->hasMany(TicketAttachment::class, 'message_id');
    // }

    /**
     * Scope a query to only include public messages.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_private', false);
    }

    /**
     * Scope a query to only include private messages.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopePrivate(Builder $query): Builder
    {
        return $query->where('is_private', true);
    }

    /**
     * Scope a query to only include messages from a specific sender type.
     *
     * @param Builder $query
     * @param string $senderType
     * @return Builder
     */
    public function scopeBySenderType(Builder $query, string $senderType): Builder
    {
        return $query->where('sender_type', $senderType);
    }
}