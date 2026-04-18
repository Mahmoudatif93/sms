<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class MetaConversationLog
 *
 * Logs Meta conversation decisions and WhatsApp messaging behavior.
 *
 * @property int $id
 * @property string|null $conversation_id
 * @property string|null $whatsapp_message_id
 * @property string|null $whatsapp_conversation_id
 * @property string $decision
 * @property string|null $category_attempted
 * @property string $message_type
 * @property string $direction
 * @property bool $was_blocked
 * @property int|null $meta_error_code
 * @property string|null $meta_error_message
 * @property int|null $created_at
 * @property int|null $updated_at
 *
 * @property-read Conversation|null $conversation
 * @property-read WhatsappMessage|null $whatsappMessage
 * @property-read WhatsappConversation|null $whatsappConversation
 *
 * @method static Builder|MetaConversationLog query()
 * @method static Builder|MetaConversationLog whereDecision(string $value)
 * @method static Builder|MetaConversationLog whereWasBlocked(bool $value)
 * @method static Builder|MetaConversationLog whereConversationId(string $value)
 * @method static Builder|MetaConversationLog whereWhatsappMessageId(string $value)
 * @method static Builder|MetaConversationLog whereWhatsappConversationId(string $value)
 *
 * @mixin Eloquent
 */
class MetaConversationLog extends Model
{
    protected $table = 'meta_conversation_logs';

    protected $fillable = [
        'conversation_id',
        'whatsapp_message_id',
        'whatsapp_conversation_id',
        'decision',
        'category_attempted',
        'message_type',
        'direction',
        'was_blocked',
        'meta_error_code',
        'meta_error_message',
        'text_log'
    ];

    protected $casts = [
        'was_blocked' => 'boolean',
        'meta_error_code' => 'integer',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    /**
     * Get the internal conversation this log is linked to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    /**
     * Get the WhatsApp message associated with this log.
     */
    public function whatsappMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessage::class, 'whatsapp_message_id');
    }

    /**
     * Get the Meta WhatsApp conversation related to this log.
     */
    public function whatsappConversation(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversation::class, 'whatsapp_conversation_id');
    }
}
