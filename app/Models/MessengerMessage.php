<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Class MessengerMessage
 *
 * Represents a message sent or received via Facebook Messenger.
 *
 * @package App\Models
 *
 * @property string $id
 * @property string $meta_page_id
 * @property string|null $conversation_id
 * @property string $sender_type
 * @property string $sender_id
 * @property string $recipient_type
 * @property string $recipient_id
 * @property string $sender_role
 * @property string $type
 * @property string $direction
 * @property string $status
 * @property string|null $messenger_conversation_id
 * @property string|null $messageable_type
 * @property string|null $messageable_id
 * @property string|null $messenger_message_type
 * @property string|null $replied_to_message_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read MetaPage $metaPage
 * @property-read Conversation|null $conversation
 * @property-read Model|Eloquent $sender
 * @property-read Model|Eloquent $recipient
 * @property-read Model|Eloquent|null $messageable
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MessengerMessageStatus> $statuses
 * @property-read int|null $statuses_count
 *
 * @method static Builder|MessengerMessage newModelQuery()
 * @method static Builder|MessengerMessage newQuery()
 * @method static Builder|MessengerMessage query()
 * @method static Builder|MessengerMessage whereId($value)
 * @method static Builder|MessengerMessage whereMetaPageId($value)
 * @method static Builder|MessengerMessage whereConversationId($value)
 * @method static Builder|MessengerMessage whereSenderType($value)
 * @method static Builder|MessengerMessage whereSenderId($value)
 * @method static Builder|MessengerMessage whereRecipientType($value)
 * @method static Builder|MessengerMessage whereRecipientId($value)
 * @method static Builder|MessengerMessage whereSenderRole($value)
 * @method static Builder|MessengerMessage whereType($value)
 * @method static Builder|MessengerMessage whereDirection($value)
 * @method static Builder|MessengerMessage whereStatus($value)
 * @method static Builder|MessengerMessage whereMessengerConversationId($value)
 * @method static Builder|MessengerMessage whereMessageableType($value)
 * @method static Builder|MessengerMessage whereMessageableId($value)
 * @method static Builder|MessengerMessage whereMessengerMessageType($value)
 * @method static Builder|MessengerMessage whereCreatedAt($value)
 * @method static Builder|MessengerMessage whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class MessengerMessage extends Model
{
    const MESSAGE_SENDER_ROLE_BUSINESS = 'BUSINESS';
    const MESSAGE_SENDER_ROLE_CONSUMER = 'CONSUMER';

    const MESSAGE_DIRECTION_SENT = 'SENT';
    const MESSAGE_DIRECTION_RECEIVED = 'RECEIVED';

    const MESSAGE_STATUS_INITIATED = 'initiated';
    const MESSAGE_STATUS_SENT = 'sent';
    const MESSAGE_STATUS_DELIVERED = 'delivered';
    const MESSAGE_STATUS_READ = 'read';
    const MESSAGE_STATUS_FAILED = 'failed';
    const MESSAGE_STATUS_DELETED = 'deleted';
    const MESSAGE_STATUS_WARNING = 'warning';

    const MESSAGE_TYPE_TEXT = 'text';

         public const MESSAGEABLE_RELATIONS = [
        MessengerTextMessage::class => [],
        MessengerAttachmentMessage::class => [],
    ];

    protected $table = 'messenger_messages';

    protected $primaryKey = 'id';
    /**
     * The data type of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'meta_page_id',
        'conversation_id',
        'sender_type',
        'sender_id',
        'recipient_type',
        'recipient_id',
        'sender_role',
        'type',
        'direction',
        'status',
        'messenger_conversation_id',
        'messageable_id',
        'messageable_type',
        'messenger_message_type',
        'replied_to_message_id',
    ];

    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    /**
     * The Facebook Page (MetaPage) the message belongs to.
     */
    public function metaPage(): BelongsTo
    {
        return $this->belongsTo(MetaPage::class, 'meta_page_id');
    }

    /**
     * The conversation this message belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    /**
     * The polymorphic sender (could be agent, bot, user).
     */
    public function sender(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The polymorphic recipient.
     */
    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The polymorphic content model (text, media, template, etc.)
     */
    public function messageable(): MorphTo
    {
        return $this->morphTo();
    }

    public function repliedToMessage(): BelongsTo
    {
        return $this->belongsTo(MessengerMessage::class, 'replied_to_message_id');
    }

    /**
     * Get the statuses associated with the message.
     */
    public function statuses(): HasMany
    {
        return $this->hasMany(MessengerMessageStatus::class, 'messenger_message_id', 'id');
    }

    public function scopeWithMessageableRelations(Builder $query): Builder
    {
        return $query->with([
            'statuses',
            'messageable' => function (MorphTo $morphTo) {
                $morphTo->morphWith(self::MESSAGEABLE_RELATIONS);
            },
            'repliedToMessage.messageable' => function (MorphTo $morphTo) {
                $morphTo->morphWith(self::MESSAGEABLE_RELATIONS);
            },
        ]);
    }
}
