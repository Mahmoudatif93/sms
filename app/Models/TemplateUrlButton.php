<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class TemplateUrlButton
 *
 * Represents a WhatsApp message template URL button.
 *
 * @property int $id
 * @property int $button_component_id
 * @property string $text
 * @property string $url
 * @property string|null $example
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read WhatsappTemplateButtonComponent $buttonComponent
 * @mixin Eloquent
 */
class TemplateUrlButton extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'template_url_buttons';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'button_component_id',
        'text',
        'url',
        'example',
    ];

    /**
     * Get the parent button component that owns this URL button.
     *
     * @return BelongsTo
     */
    public function buttonComponent(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplateButtonComponent::class, 'button_component_id');
    }
}
