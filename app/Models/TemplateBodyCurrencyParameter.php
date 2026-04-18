<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


/**
 * App\Models\TemplateBodyCurrencyParameter
 *
 * Represents a currency parameter within a WhatsApp template message body component.
 *
 * @property int $id The primary key of the currency parameter.
 * @property int $template_message_body_component_id Foreign key to the body component that this currency parameter belongs to.
 * @property string $fallback_value The fallback value for the currency (e.g., a string representation of the amount in case of display issues).
 * @property string $code The currency code (e.g., USD, EUR).
 * @property int $amount_1000 The amount multiplied by 1000 (e.g., for an amount of 1.50 USD, the value would be 1500).
 * @property int|null $created_at The timestamp when the currency parameter was created.
 * @property int|null $updated_at The timestamp when the currency parameter was last updated.
 *
 * @property-read TemplateMessageBodyComponent|null $bodyComponent The body component associated with this currency parameter.
 *
 * @method static Builder|TemplateBodyCurrencyParameter newModelQuery() Begin a new model query.
 * @method static Builder|TemplateBodyCurrencyParameter newQuery() Begin a new query for this model.
 * @method static Builder|TemplateBodyCurrencyParameter query() Get a new query builder for this model.
 *
 * @mixin Eloquent
 */
class TemplateBodyCurrencyParameter extends Model
{
    protected $table = 'template_body_currency_parameters';

    protected $fillable = [
        'template_message_body_component_id',
        'fallback_value',
        'code',
        'amount_1000',
    ];

    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    /**
     * Get the body component associated with this currency parameter.
     */
    public function bodyComponent(): BelongsTo
    {
        return $this->belongsTo(TemplateMessageBodyComponent::class, 'template_message_body_component_id');
    }
}
