<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Represents a WhatsApp conversation.
 *
 * This model stores information about conversations, including details such as
 * the conversation type, expiration timestamp, and associated WhatsApp phone number.
 * Each conversation can have multiple messages linked to it.
 *
 * @property string $id The unique identifier for the conversation.
 * @property int $whatsapp_phone_number_id The ID of the WhatsApp phone number associated with the conversation.
 * @property string $type The type of conversation (e.g., authentication, marketing, utility, service, referral_conversion).
 * @property int|null $expiration_timestamp The timestamp when the conversation expires.
 * @property Carbon|null $created_at The timestamp when the record was created.
 * @property Carbon|null $updated_at The timestamp when the record was last updated.
 * @property-read WhatsappPhoneNumber $whatsappPhoneNumber The associated WhatsApp phone number.
 * @property-read Collection|WhatsappMessage[] $messages The messages belonging to the conversation.
 * @method static Builder|WhatsappConversation newModelQuery()
 * @method static Builder|WhatsappConversation newQuery()
 * @method static Builder|WhatsappConversation query()
 * @method static Builder|WhatsappConversation whereCreatedAt($value)
 * @method static Builder|WhatsappConversation whereExpirationTimestamp($value)
 * @method static Builder|WhatsappConversation whereId($value)
 * @method static Builder|WhatsappConversation whereType($value)
 * @method static Builder|WhatsappConversation whereUpdatedAt($value)
 * @method static Builder|WhatsappConversation whereWhatsappPhoneNumberId($value)
 * @mixin Eloquent
 */
class WhatsappConversation extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'whatsapp_conversations';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

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
        'whatsapp_consumer_phone_number_id',
        'type',
        'expiration_timestamp',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'expiration_timestamp' => 'integer',
    ];

    /**
     * Get the WhatsApp phone number associated with the conversation.
     *
     * @return BelongsTo
     */
    public function whatsappPhoneNumber(): BelongsTo
    {
        return $this->belongsTo(WhatsappPhoneNumber::class, 'whatsapp_phone_number_id');
    }

    /**
     * Get the consumer's phone number associated with the conversation.
     *
     * @return BelongsTo
     */
    public function consumerPhoneNumber(): BelongsTo
    {
        return $this->belongsTo(WhatsappConsumerPhoneNumber::class, 'whatsapp_consumer_phone_number_id');
    }

    /**
     * Get the messages for the conversation.
     *
     * @return HasMany
     */
    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class, 'whatsapp_conversation_id');
    }

    public function billing()
    {
        return $this->hasOne(WhatsappConversationBilling::class, 'conversation_id', 'id');
    }
}
