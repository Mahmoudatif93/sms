<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\WhatsappFlowResponseMessage
 *
 * Represents a WhatsApp Flows response received via webhook (interactive nfm_reply).
 *
 * @property int $id
 * @property string $whatsapp_message_id ID of the parent message from webhook
 * @property string|null $flow_token Flow session token (UUID)
 * @property string|null $name Button/action name from the flow (e.g. "flow")
 * @property string|null $body Button label or user-facing content (e.g. "Sent")
 * @property array|null $response_json Decoded JSON returned in the webhook's response_json field
 * @property int|null $created_at
 * @property int|null $updated_at
 *
 * @property-read WhatsappMessage $whatsappMessage
 *
 * @method static Builder|WhatsappFlowResponseMessage newModelQuery()
 * @method static Builder|WhatsappFlowResponseMessage newQuery()
 * @method static Builder|WhatsappFlowResponseMessage query()
 * @method static Builder|WhatsappFlowResponseMessage whereId($value)
 * @method static Builder|WhatsappFlowResponseMessage whereWhatsappMessageId($value)
 * @method static Builder|WhatsappFlowResponseMessage whereFlowToken($value)
 * @method static Builder|WhatsappFlowResponseMessage whereName($value)
 * @method static Builder|WhatsappFlowResponseMessage whereBody($value)
 * @method static Builder|WhatsappFlowResponseMessage whereResponseJson($value)
 * @method static Builder|WhatsappFlowResponseMessage whereCreatedAt($value)
 * @method static Builder|WhatsappFlowResponseMessage whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class WhatsappFlowResponseMessage extends Model
{
    protected $table = 'whatsapp_flow_response_messages';

    protected $fillable = [
        'whatsapp_message_id',
        'flow_token',
        'name',
        'body',
        'response_json',
    ];

    protected $casts = [
        'response_json' => 'array',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    /**
     * Get the related WhatsApp message.
     */
    public function whatsappMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessage::class, 'whatsapp_message_id');
    }
}
