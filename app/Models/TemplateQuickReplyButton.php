<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class TemplateQuickReplyButton
 *
 * Represents a quick reply button attached to a WhatsApp message template.
 *
 * @property int $id
 * @property int $button_component_id
 * @property string $text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read WhatsappTemplateButtonComponent $buttonComponent
 * @mixin Eloquent
 */
class TemplateQuickReplyButton extends Model
{
    /**
     * @var string
     */
    protected $table = 'template_quick_reply_buttons';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'button_component_id',
        'text',
    ];

    /**
     * Get the associated button component.
     *
     * @return BelongsTo
     */
    public function buttonComponent(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplateButtonComponent::class, 'button_component_id');
    }
}
