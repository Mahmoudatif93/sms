<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 * App\Models\WhatsappFlowMessage
 *
 * Represents a WhatsApp interactive Flow message sent or received.
 *
 * @property int $id
 * @property string $whatsapp_message_id Foreign key to whatsapp_messages table
 * @property int $whatsapp_flow_id Foreign key to whatsapp_flows table
 * @property string $header_text Header text for the flow message
 * @property string $body_text Body content of the message
 * @property string|null $footer_text Optional footer content
 * @property string $flow_cta Call-to-action text
 * @property string|null $flow_token Unique token associated with this flow instance
 * @property string $screen_id The screen ID the flow should start at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read WhatsappMessage $whatsappMessage
 * @property-read WhatsappFlow $whatsappFlow
 *
 * @method static \Illuminate\Database\Eloquent\Builder|WhatsappFlowMessage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WhatsappFlowMessage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WhatsappFlowMessage query()
 * @method static \Illuminate\Database\Eloquent\Builder|WhatsappFlowMessage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhatsappFlowMessage whereWhatsappMessageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhatsappFlowMessage whereWhatsappFlowId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhatsappFlowMessage whereHeaderText($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhatsappFlowMessage whereBodyText($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhatsappFlowMessage whereFooterText($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhatsappFlowMessage whereFlowCta($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhatsappFlowMessage whereFlowToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhatsappFlowMessage whereScreenId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhatsappFlowMessage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhatsappFlowMessage whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class WhatsappFlowMessage extends Model
{
    protected $table = 'whatsapp_flow_messages';

    protected $fillable = [
        'whatsapp_message_id',
        'whatsapp_flow_id',
        'header_text',
        'body_text',
        'footer_text',
        'flow_cta',
        'flow_token',
        'screen_id',
    ];

    /**
     * Casts.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The parent WhatsappMessage (polymorphic).
     */
    public function message(): MorphOne
    {
        return $this->morphOne(WhatsappMessage::class, 'messageable');
    }

    /**
     * The original message this flow message belongs to.
     */
    public function whatsappMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessage::class, 'whatsapp_message_id');
    }

    /**
     * The WhatsApp flow definition this message used.
     */
    public function whatsappFlow(): BelongsTo
    {
        return $this->belongsTo(WhatsappFlow::class, 'whatsapp_flow_id');
    }
}
