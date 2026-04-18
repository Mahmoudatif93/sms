<?php

namespace App\Models;

use App\Enums\WalletTransactionStatus;
use App\Traits\WhatsappMediaManager;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;


/**
 * Represents a WhatsApp message associated with a phone number.
 *
 * This model stores information about messages sent or received through WhatsApp.
 * Each message is linked to a phone number and includes details such as the sender,
 * recipient, message type, direction, and status.
 *
 * @property int $id The primary key ID for the message.
 * @property int $whatsapp_phone_number_id The ID of the WhatsApp phone number associated with the message.
 * @property string $sender_type The type of sender (e.g., business, consumer). This is part of a polymorphic relationship.
 * @property int $sender_id The ID of the sender, part of a polymorphic relationship.
 * @property int $agent_id The ID of the agent who sent the message.
 * @property string $recipient_type The type of recipient (e.g., business, consumer). This is part of a polymorphic relationship.
 * @property int $recipient_id The ID of the recipient, part of a polymorphic relationship.
 * @property string $sender_role The role of the sender (e.g., business, consumer).
 * @property string $type The type of message (e.g., text, image, video).
 * @property string $direction The direction of the message (e.g., sent, received).
 * @property string $status The current status of the message (e.g., initiated, sent, delivered, read, failed, deleted, warning).
 * @property string|null $replied_to_message_id The ID of the message this message is replying to (from context.id).
 * @property string|null $replied_to_message_from The phone number of the sender of the message being replied to (from context.from).
 * @property int $created_at The timestamp when the record was created.
 * @property int $updated_at The timestamp when the record was last updated.
 * @property-read Model|Eloquent $recipient
 * @property-read Model|Eloquent $sender
 * @property-read WhatsappPhoneNumber $whatsappPhoneNumber
 * @property-read WhatsappMessage|null $repliedToMessage
 * @method static Builder|WhatsappMessage newModelQuery()
 * @method static Builder|WhatsappMessage newQuery()
 * @method static Builder|WhatsappMessage query()
 * @method static Builder|WhatsappMessage whereCreatedAt($value)
 * @method static Builder|WhatsappMessage whereDirection($value)
 * @method static Builder|WhatsappMessage whereId($value)
 * @method static Builder|WhatsappMessage whereRecipientId($value)
 * @method static Builder|WhatsappMessage whereRecipientType($value)
 * @method static Builder|WhatsappMessage whereSenderId($value)
 * @method static Builder|WhatsappMessage whereSenderRole($value)
 * @method static Builder|WhatsappMessage whereSenderType($value)
 * @method static Builder|WhatsappMessage whereStatus($value)
 * @method static Builder|WhatsappMessage whereType($value)
 * @method static Builder|WhatsappMessage whereUpdatedAt($value)
 * @method static Builder|WhatsappMessage whereWhatsappPhoneNumberId($value)
 * @property string|null $whatsapp_conversation_id The ID of the WhatsApp conversation this message belongs to.
 * @property int|null $messageable_id
 * @property string|null $messageable_type
 * @property-read WhatsappConversation|null $conversation
 * @property-read Model|Eloquent|null $messageable
 * @property-read Collection<int, WhatsappMessageStatus> $statuses
 * @property-read int|null $statuses_count
 * @method static Builder|WhatsappMessage whereMessageableId($value)
 * @method static Builder|WhatsappMessage whereMessageableType($value)
 * @method static Builder|WhatsappMessage whereWhatsappConversationId($value)
 * @property-read Collection<int, TemplateMessageBodyComponent> $bodyComponents
 * @property-read int|null $body_components_count
 * @property-read Collection<int, TemplateBodyCurrencyParameter> $bodyCurrencyParameters
 * @property-read int|null $body_currency_parameters_count
 * @property-read Collection<int, TemplateBodyDateTimeParameter> $bodyDateTimeParameters
 * @property-read int|null $body_date_time_parameters_count
 * @property-read Collection<int, TemplateBodyTextParameter> $bodyTextParameters
 * @property-read int|null $body_text_parameters_count
 * @property-read WhatsappTemplateMessage|null $template
 * @property Carbon|null $deleted_at
 * @property-read WhatsappAudioMessage|null $audioMessage
 * @property-read WhatsappImageMessage|null $imageMessage
 * @property-read WhatsappVideoMessage|null $videoMessage
 * @method static Builder|WhatsappMessage onlyTrashed()
 * @method static Builder|WhatsappMessage whereDeletedAt($value)
 * @method static Builder|WhatsappMessage withTrashed()
 * @method static Builder|WhatsappMessage withoutTrashed()
 * @property string|null $campaign_id
 * @property string|null $conversation_id
 * @property-read Campaign|null $campaign
 * @property-read Channel|null $channel
 * @property-read MessageBilling|null $translationBilling
 * @property-read MessageBilling|null $chatbotBilling
 * @property-read Collection<int, MessageBilling> $billings
 * @property-read int|null $billings_count
 * @property-read WhatsappConversation|null $whatsappConversation
 * @method static Builder<static>|WhatsappMessage unreadContact()
 * @method static Builder<static>|WhatsappMessage whereCampaignId($value)
 * @method static Builder<static>|WhatsappMessage whereConversationId($value)
 * @method static Builder<static>|WhatsappMessage withMessageableRelations()
 * @mixin Eloquent
 */
class WhatsappMessage extends Model
{

    use SoftDeletes, WhatsappMediaManager;

    const MESSAGE_SENDER_ROLE_BUSINESS = "BUSINESS";
    const MESSAGE_SENDER_ROLE_CONSUMER = "CONSUMER";
    const MESSAGE_TYPE_TEXT = 'text';
    const MESSAGE_TYPE_TEMPLATE = 'template';
    const MESSAGE_TYPE_REACTION = 'reaction';
    const MESSAGE_TYPE_LOCATION = 'location';
    const MESSAGE_TYPE_CONTACTS = 'contacts';
    const MESSAGE_TYPE_AUDIO = 'audio';
    const MESSAGE_TYPE_DOCUMENT = 'document';
    const MESSAGE_TYPE_IMAGE = 'image';
    const MESSAGE_TYPE_STICKER = 'sticker';
    const MESSAGE_TYPE_INTERACTIVE = 'interactive';
    const MESSAGE_TYPE_VIDEO = 'video';
    const MESSAGE_DIRECTION_SENT = 'SENT';
    const MESSAGE_DIRECTION_RECEIVED = 'RECEIVED';
    const MESSAGE_STATUS_INITIATED = 'initiated';
    const MESSAGE_STATUS_SENT = 'sent';
    const MESSAGE_STATUS_DELIVERED = 'delivered';
    const MESSAGE_STATUS_READ = 'read';
    const MESSAGE_STATUS_FAILED = 'failed';
    const MESSAGE_STATUS_DELETED = 'deleted';
    const MESSAGE_STATUS_WARNING = 'warning';
    public const MESSAGEABLE_RELATIONS = [
        WhatsappTemplateMessage::class => [],
        WhatsappTextMessage::class => [],
        WhatsappImageMessage::class => [],
        WhatsappVideoMessage::class => [],
        WhatsappAudioMessage::class => [],
        WhatsappDocumentMessage::class => [],
        WhatsappInteractiveMessage::class => [],
        WhatsappReactionMessage::class => [],
        WhatsappStickerMessage::class => [],

    ];
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'whatsapp_messages';
    /**
     * The primary key associated with the table.
     *
     * @var int
     */
    protected $primaryKey = 'id';
    /**
     * The data type of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'whatsapp_phone_number_id',
        'sender_type',
        'sender_id',
        'recipient_type',
        'recipient_id',
        'sender_role',
        'whatsapp_conversation_id',
        'conversation_id',
        'type',
        'messageable_id',
        'messageable_type',
        'direction',
        'status',
        'campaign_id',
        'replied_to_message_id',
        'replied_to_message_from',
        'agent_id',
    ];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    /**
     * Get the sender of the message.
     *
     * @return MorphTo
     */
    public function sender(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the recipient of the message.
     *
     * @return MorphTo
     */
    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the WhatsApp phone number associated with the message.
     *
     * @return BelongsTo
     */
    public function whatsappPhoneNumber(): BelongsTo
    {
        return $this->belongsTo(WhatsappPhoneNumber::class, 'whatsapp_phone_number_id');
    }

    /**
     * Get the messageable entity related to this message.
     *
     * This relationship connects the WhatsappMessage to the specific type of message (text, image, video, etc.).
     *
     * @return MorphTo
     */
    public function messageable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the conversation that this message belongs to.
     *
     * @return BelongsTo
     */
    public function whatsappConversation(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversation::class, 'conversation_id');
    }

    /**
     * Get the statuses associated with the message.
     *
     * @return HasMany
     */
    public function statuses(): HasMany
    {
        return $this->hasMany(WhatsappMessageStatus::class, 'whatsapp_message_id');
    }

    /**
     * Get the body components associated with this message.
     *
     * @return HasMany
     */
    public function bodyComponents(): HasMany
    {
        return $this->hasMany(TemplateMessageBodyComponent::class, 'whatsapp_message_id');
    }

    /**
     * Get the body text parameters associated with this message.
     *
     * @return HasMany
     */
    public function bodyTextParameters(): HasMany
    {
        return $this->hasMany(TemplateBodyTextParameter::class, 'whatsapp_message_id');
    }

    /**
     * Get the currency parameters associated with this message.
     *
     * @return HasMany
     */
    public function bodyCurrencyParameters(): HasMany
    {
        return $this->hasMany(TemplateBodyCurrencyParameter::class, 'whatsapp_message_id');
    }

    /**
     * Get the date/time parameters associated with this message.
     *
     * @return HasMany
     */
    public function bodyDateTimeParameters(): HasMany
    {
        return $this->hasMany(TemplateBodyDateTimeParameter::class, 'whatsapp_message_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplateMessage::class, 'messageable_id');
    }

    // Define the relationship for image messages

    public function imageMessage(): HasOne
    {
        return $this->hasOne(WhatsappImageMessage::class, 'whatsapp_message_id');
    }

    // Define the relationship for video messages
    public function videoMessage(): HasOne
    {
        return $this->hasOne(WhatsappVideoMessage::class, 'whatsapp_message_id');
    }

    // Define the relationship for audio messages
    public function audioMessage(): HasOne
    {
        return $this->hasOne(WhatsappAudioMessage::class, 'whatsapp_message_id');
    }

    public function documentMessage(): HasOne
    {
        return $this->hasOne(WhatsappDocumentMessage::class, 'whatsapp_message_id');
    }

    // Define the relationship for interactive messages
    public function interactiveMessage(): HasOne
    {
        return $this->hasOne(WhatsappInteractiveMessage::class, 'whatsapp_message_id');
    }

    // Define the relationship for reaction messages
    public function reactionMessage(): HasMany
    {
        return $this->HasMany(WhatsappReactionMessage::class, 'message_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    /**
     * Get the channel associated with the WhatsApp message through the WhatsApp phone number.
     *
     * @return HasOneThrough
     */
    public function channel(): HasOneThrough
    {

        return $this->hasOneThrough(
            Channel::class,
            WhatsappConfiguration::class,
            'primary_whatsapp_phone_number_id', // Foreign key on WhatsappConfiguration
            'connector_id',                    // Foreign key on Channel
            'whatsapp_phone_number_id',        // Local key on WhatsappMessage
            'connector_id'                     // Local key on WhatsappConfiguration
        );
    }


    /**
     * Get the translation billing details associated with the WhatsApp message.
     *
     * @return MorphOne
     */
    public function translationBilling(): MorphOne
    {
        return $this->morphOne(MessageBilling::class, 'messageable')
            ->where('type', MessageBilling::TYPE_TRANSLATION);
    }

    /**
     * Get the chatbot billing details associated with the WhatsApp message.
     *
     * @return MorphOne
     */
    public function chatbotBilling(): MorphOne
    {
        // if($this->id == "wamid.HBgMOTcwNTk4NzA0NTcwFQIAEhgUM0ExNDM0NTBBQ0I1NDc0MDIyODIA"){
        //     dd($this->morphOne(MessageBilling::class, 'messageable')
        //     ->where('type', MessageBilling::TYPE_CHATBOT)->get());
        // }
        return $this->morphOne(MessageBilling::class, 'messageable')
            ->where('type', MessageBilling::TYPE_CHATBOT);
    }

    /**
     * Get all billing records associated with the WhatsApp message.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function billings(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(MessageBilling::class, 'messageable');
    }

    public function scopeUnreadContact($query)
    {
        return 0;
        //TODO: Implement this method
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function scopeWithMessageableRelations(Builder $query): Builder
    {
        return $query->with([
            'statuses.errors',
            'messageable' => function (MorphTo $morphTo) {
                $morphTo->morphWith(self::MESSAGEABLE_RELATIONS);
            },
            'repliedToMessage.messageable' => function (MorphTo $morphTo) {
                $morphTo->morphWith(self::MESSAGEABLE_RELATIONS);
            },
            'reactionMessage',
        ])->where('messageable_type', '<>', WhatsappReactionMessage::class);
    }

    public function updateWalletTransactionMeta(?string $transactionId): void
    {
        if (!$transactionId) {
            return;
        }

        $transaction = WalletTransaction::find($transactionId);

        if (!$transaction || $transaction->status !== WalletTransactionStatus::PENDING) {
            return;
        }

        $meta = $transaction->meta ?? [];
        $meta['whatsapp_message_id'] = $this->id;

        $transaction->meta = $meta;
        $transaction->save();
    }

    // App\Models\WhatsappMessage.php

    public function walletTransaction(): HasOne
    {
        return $this->hasOne(WalletTransaction::class, 'meta->whatsapp_message_id', 'id')
            ->whereIn('status', [WalletTransactionStatus::ACTIVE, WalletTransactionStatus::PENDING]);
    }

    /**
     * Get the message that this message is replying to.
     *
     * @return BelongsTo
     */
    public function repliedToMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessage::class, 'replied_to_message_id', 'id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id', 'id');
    }

    /**
     * Scope to get failed template messages with their details.
     * Returns: Channel, Campaign, Phone Number, Failure Reason, and Date
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeFailedTemplateMessages(Builder $query): Builder
    {
        return $query
            ->where('type', self::MESSAGE_TYPE_TEMPLATE)
            ->where('status', self::MESSAGE_STATUS_FAILED)
            ->with([
                'campaign.channel',
                'campaign',
                'recipient',
                'statuses' => function ($query) {
                    $query->where('status', self::MESSAGE_STATUS_FAILED)
                        ->with('errors')
                        ->latest();
                }
            ])
            ->select([
                'id',
                'campaign_id',
                'recipient_type',
                'recipient_id',
                'status',
                'created_at',
                'updated_at'
            ]);
    }
}
