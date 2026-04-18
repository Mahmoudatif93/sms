<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 * App\Models\WhatsappInteractiveMessage
 *
 * Represents a WhatsApp interactive message (button_reply, list_reply, etc.)
 *
 * @property int $id
 * @property string $whatsapp_message_id Foreign key to whatsapp_messages
 * @property string $interactive_type Type of interactive message (button_reply, list_reply, nfm_reply)
 * @property string|null $button_reply_id Button ID from button_reply
 * @property string|null $button_reply_title Button title from button_reply
 * @property string|null $list_reply_id List item ID from list_reply
 * @property string|null $list_reply_title List item title from list_reply
 * @property string|null $list_reply_description List item description from list_reply
 * @property array|null $payload Full interactive payload from webhook
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WhatsappMessage $whatsappMessage
 * @method static Builder|WhatsappInteractiveMessage newModelQuery()
 * @method static Builder|WhatsappInteractiveMessage newQuery()
 * @method static Builder|WhatsappInteractiveMessage query()
 * @method static Builder|WhatsappInteractiveMessage whereId($value)
 * @method static Builder|WhatsappInteractiveMessage whereWhatsappMessageId($value)
 * @method static Builder|WhatsappInteractiveMessage whereInteractiveType($value)
 * @method static Builder|WhatsappInteractiveMessage whereButtonReplyId($value)
 * @method static Builder|WhatsappInteractiveMessage whereButtonReplyTitle($value)
 * @method static Builder|WhatsappInteractiveMessage whereListReplyId($value)
 * @method static Builder|WhatsappInteractiveMessage whereListReplyTitle($value)
 * @method static Builder|WhatsappInteractiveMessage whereListReplyDescription($value)
 * @method static Builder|WhatsappInteractiveMessage wherePayload($value)
 * @method static Builder|WhatsappInteractiveMessage whereCreatedAt($value)
 * @method static Builder|WhatsappInteractiveMessage whereUpdatedAt($value)
 * @mixin Eloquent
 */
class WhatsappInteractiveMessage extends Model
{
    // Interactive message types
    public const TYPE_BUTTON_REPLY = 'button_reply';
    public const TYPE_LIST_REPLY = 'list_reply';
    public const TYPE_NFM_REPLY = 'nfm_reply';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'whatsapp_interactive_messages';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'whatsapp_message_id',
        'interactive_type',
        'interactive_message_draft_id',
        'button_reply_id',
        'button_reply_title',
        'list_reply_id',
        'list_reply_title',
        'list_reply_description',
        'payload',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'payload' => 'array',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    /**
     * Get the related WhatsApp message.
     *
     * @return BelongsTo
     */
    public function whatsappMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessage::class, 'whatsapp_message_id', 'id');
    }

    /**
     * Get the draft this interactive message was created from.
     *
     * @return BelongsTo
     */
    public function draft(): BelongsTo
    {
        return $this->belongsTo(InteractiveMessageDraft::class, 'interactive_message_draft_id');
    }

    /**
     * Get the parent WhatsappMessage model (polymorphic).
     *
     * @return MorphOne
     */
    public function message(): MorphOne
    {
        return $this->morphOne(WhatsappMessage::class, 'messageable');
    }

    /**
     * Check if this is a button reply.
     *
     * @return bool
     */
    public function isButtonReply(): bool
    {
        return $this->interactive_type === self::TYPE_BUTTON_REPLY;
    }

    /**
     * Check if this is a list reply.
     *
     * @return bool
     */
    public function isListReply(): bool
    {
        return $this->interactive_type === self::TYPE_LIST_REPLY;
    }

    /**
     * Get the reply ID (either button or list).
     *
     * @return string|null
     */
    public function getReplyId(): ?string
    {
        return $this->button_reply_id ?? $this->list_reply_id;
    }

    /**
     * Get the reply title (either button or list).
     *
     * @return string|null
     */
    public function getReplyTitle(): ?string
    {
        return $this->button_reply_title ?? $this->list_reply_title;
    }

    public function getInteractiveType(): string
    {
        return $this->interactive_type;
    }
    public function getInteractiveHeader(): array
    {
        return $this->payload['header'] ?? [];
    }

    public function getInteractiveBody(): array
    {
        return $this->payload['body'] ?? [];
    }

    public function getInteractiveFooter(): array
    {
        return $this->payload['footer'] ?? [];
    }

    public function getInteractiveButtons(): array
    {
        return $this->payload['action']['buttons'] ?? [];
    }
}

