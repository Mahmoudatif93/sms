<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * App\Models\WhatsappTemplateMessage
 *
 * Represents a WhatsApp template message that is associated with a WhatsApp message and a WhatsApp message template.
 *
 * @property int $id The primary key of the template message.
 * @property string|null $whatsapp_message_id Foreign key to the WhatsApp message.
 * @property string|null $whatsapp_template_id Foreign key to the WhatsApp message template.
 * @property string $template_name The name of the WhatsApp template.
 * @property string $template_language_code The language code of the template.
 * @property int|null $created_at The timestamp when the template message was created.
 * @property int|null $updated_at The timestamp when the template message was last updated.
 * @property-read WhatsappMessage|null $whatsappMessage The WhatsApp message associated with this template message.
 * @property-read WhatsappMessageTemplate|null $whatsappTemplate The WhatsApp template associated with this template message.
 * @method static Builder|WhatsappTemplateMessage newModelQuery() Begin a new model query.
 * @method static Builder|WhatsappTemplateMessage newQuery() Begin a new query for this model.
 * @method static Builder|WhatsappTemplateMessage query() Get a new query builder for this model.
 * @property-read TemplateMessageBodyComponent|null $bodyComponents
 * @method static Builder|WhatsappTemplateMessage whereCreatedAt($value)
 * @method static Builder|WhatsappTemplateMessage whereId($value)
 * @method static Builder|WhatsappTemplateMessage whereTemplateLanguageCode($value)
 * @method static Builder|WhatsappTemplateMessage whereTemplateName($value)
 * @method static Builder|WhatsappTemplateMessage whereUpdatedAt($value)
 * @method static Builder|WhatsappTemplateMessage whereWhatsappMessageId($value)
 * @method static Builder|WhatsappTemplateMessage whereWhatsappTemplateId($value)
 * @mixin Eloquent
 */
class WhatsappTemplateMessage extends Model
{
    protected $table = 'whatsapp_template_messages';

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'whatsapp_message_id',
        'whatsapp_template_id',
        'template_name',
        'template_language_code',
    ];

    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];

    protected $with = [
        'headerComponents',
        'headerComponents.headerImageParameter',
        'bodyComponents',
        'bodyComponents.bodyTextParameters',
        'bodyComponents.bodyCurrencyParameters',
        'bodyComponents.bodyDateTimeParameters',
    ];


    /**
     * Get the WhatsApp message associated with the template message.
     *
     * @return BelongsTo
     */
    public function whatsappMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessage::class, 'whatsapp_message_id');
    }

    /**
     * Get the WhatsApp template associated with the template message.
     *
     * @return BelongsTo
     */
    public function whatsappTemplate(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessageTemplate::class, 'whatsapp_template_id');
    }

    /**
     * Get the components associated with the template message.
     *
     * @return HasOne
     */
    public function bodyComponents(): HasOne
    {
        return $this->hasOne(TemplateMessageBodyComponent::class, 'template_message_id');
    }

    public function headerComponents(): HasOne
    {
        return $this->hasOne(TemplateMessageHeaderComponent::class, 'template_message_id');
    }
}
