<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * App\Models\LiveChatMessage
 *
 * @property string $id
 * @property string $channel_id
 * @property string $widget_id
 * @property string $conversation_id
 * @property string $sender_type
 * @property string $sender_id
 * @property string $type
 * @property string $status
 * @property string $messageable_type
 * @property string $messageable_id
 * @property boolean $is_read
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Channel $channel
 * @property-read Widget $widget
 * @property-read Model|Eloquent $messageable
 * @property-read Model|Eloquent $sender
 */

class LiveChatMessage extends Model
{
    use SoftDeletes, HasUuids;

    const MESSAGE_STATUS_INITIATED = 'initiated';
    const MESSAGE_STATUS_SENT = 'sent';
    const MESSAGE_STATUS_DELIVERED = 'delivered';
    const MESSAGE_STATUS_READ = 'read';
    const MESSAGE_STATUS_FAILED = 'failed';
    const MESSAGE_STATUS_DELETED = 'deleted';
    const MESSAGE_STATUS_WARNING = 'warning';
    const SENDER_TYPE_SYSTEM = 'system';

    const MESSAGE_DIRECTION_SENT = 'SENT';
    const MESSAGE_DIRECTION_RECEIVED = 'RECEIVED';

    public const MESSAGEABLE_RELATIONS = [
        LiveChatTextMessage::class => [],
        LiveChatFileMessage::class => [],
        LiveChatReactionMessage::class => [],
    ];
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'livechat_messages';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'channel_id',
        'widget_id',
        'conversation_id',
        'sender_type',
        'sender_id',
        'type',
        'status',
        'agent_id',
        'direction',
        'messageable_type',
        'messageable_id',
        'is_read',
        'read_at',
        'replied_to_message_id'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'created_at' => 'datetime:Y-m-d H:i:s.u',
        'updated_at' => 'datetime:Y-m-d H:i:s.u',
    ];

    /**
     * The storage format of the model's date columns with microseconds.
     *
     * @return string
     */
    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.u';
    }

    /**
     * Get the conversation that owns the message.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the channel that owns the message.
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * Get the widget that owns the message.
     */
    public function widget(): BelongsTo
    {
        return $this->belongsTo(Widget::class);
    }

    /**
     * Get the messageable model (polymorphic).
     */
    public function messageable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the sender of the message (polymorphic).
     */
    public function sender()
    {
        // If this is a system message, don't try to load from database
        return $this->morphTo()->withDefault(function ($model, $relation) {
            if ($this->sender_type === self::SENDER_TYPE_SYSTEM) {
                $model->id = 'system';
                $model->name = 'System';
                $model->exists = true;
            }
            return $model;
        });
    }



    /**
     * Get sender name (handles system messages)
     *
     * @return string
     */
    public function getSenderNameAttribute(): string
    {
        if ($this->isSystemMessage()) {
            return 'System';
        }

        return $this->sender ? $this->sender->name : 'Unknown';
    }

    /**
     * Check if the message is from the system.
     *
     * @return bool
     */
    public function isSystemMessage(): bool
    {
        return $this->sender_type === self::SENDER_TYPE_SYSTEM;
    }

    /**
     * Get the statuses associated with the message.
     *
     * @return HasMany
     */
    public function statuses(): HasMany
    {
        return $this->hasMany(LiveChatMessageStatus::class, 'livechat_message_id');
    }

    /**
     * Mark message as read.
     */
    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'status' => self::MESSAGE_STATUS_READ,
            'read_at' => now(),
        ]);
    }

    /**
     * Mark message as read.
     */
    public function markAsDeliverd(): void
    {
        $this->update([
            'status' => self::MESSAGE_STATUS_DELIVERED,
            'read_at' => now(),
        ]);
    }

    /**
     * Scope a query to only include unread messages.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeUnreadContact($query)
    {
        return $query->where(['sender_type' => ContactEntity::class, 'is_read' => false]);
    }

    /**
     * Scope a query to only include messages for a specific conversation.
     */
    public function scopeForConversation($query, $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id', 'id');
    }

    public function reactionMessage(): HasMany
    {
        return $this->hasMany(LiveChatReactionMessage::class, 'livechat_message_id');
    }

    /**
     * Get the message that this message is replying to.
     */
    public function repliedToMessage(): BelongsTo
    {
        return $this->belongsTo(LiveChatMessage::class, 'replied_to_message_id');
    }


    public function scopeWithMessageableRelations(Builder $query): Builder
    {
        return $query->with([
            // 'statuses.errors',
            'messageable' => function (MorphTo $morphTo) {
                $morphTo->morphWith(self::MESSAGEABLE_RELATIONS);
            },
            'repliedToMessage.messageable' => function (MorphTo $morphTo) {
                $morphTo->morphWith(self::MESSAGEABLE_RELATIONS);
            },
            'reactionMessage',
        ])->where('messageable_type', '<>', LiveChatReactionMessage::class);
    }
}
