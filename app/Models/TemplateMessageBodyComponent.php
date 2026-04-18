<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\TemplateMessageBodyComponent
 *
 * Represents a component of the body in a WhatsApp template message, which can hold various types of parameters like text, currency, or date_time.
 *
 * @property int $id The primary key of the body component.
 * @property int $template_message_id Foreign key to the WhatsApp template message.
 * @property string $type The type of the body component (e.g., text, currency, date_time).
 * @property int|null $created_at The timestamp when the body component was created.
 * @property int|null $updated_at The timestamp when the body component was last updated.
 *
 * @property-read WhatsappTemplateMessage|null $templateMessage The WhatsApp template message associated with this body component.
 *
 * @method static Builder|TemplateMessageBodyComponent newModelQuery() Begin a new model query.
 * @method static Builder|TemplateMessageBodyComponent newQuery() Begin a new query for this model.
 * @method static Builder|TemplateMessageBodyComponent query() Get a new query builder for this model.
 *
 * @mixin Eloquent
 */
class TemplateMessageBodyComponent extends Model
{
    protected $table = 'template_message_body_components';

    protected $fillable = [
        'template_message_id',
        'type',
    ];

    /**
     * Get the template message associated with the body component.
     */
    public function templateMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplateMessage::class, 'template_message_id');
    }

    /**
     * Get the text parameters associated with this body component.
     *
     * @return HasMany
     */
    public function bodyTextParameters(): HasMany
    {
        return $this->hasMany(TemplateBodyTextParameter::class, 'template_message_body_component_id');
    }

    /**
     * Get the currency parameters associated with this body component.
     *
     * @return HasMany
     */
    public function bodyCurrencyParameters(): HasMany
    {
        return $this->hasMany(TemplateBodyCurrencyParameter::class, 'template_message_body_component_id');
    }

    /**
     * Get the date_time parameters associated with this body component.
     *
     * @return HasMany
     */
    public function bodyDateTimeParameters(): HasMany
    {
        return $this->hasMany(TemplateBodyDateTimeParameter::class, 'template_message_body_component_id');
    }
}
