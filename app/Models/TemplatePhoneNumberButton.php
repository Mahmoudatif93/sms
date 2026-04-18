<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property int $id
 * @property int $button_component_id
 * @property string $text Text displayed on the button
 * @property string $phone_number Phone number to be called when the button is tapped
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WhatsappTemplateButtonComponent $buttonComponent
 * @method static Builder|TemplatePhoneNumberButton newModelQuery()
 * @method static Builder|TemplatePhoneNumberButton newQuery()
 * @method static Builder|TemplatePhoneNumberButton query()
 * @method static Builder|TemplatePhoneNumberButton whereButtonComponentId($value)
 * @method static Builder|TemplatePhoneNumberButton whereCreatedAt($value)
 * @method static Builder|TemplatePhoneNumberButton whereId($value)
 * @method static Builder|TemplatePhoneNumberButton wherePhoneNumber($value)
 * @method static Builder|TemplatePhoneNumberButton whereText($value)
 * @method static Builder|TemplatePhoneNumberButton whereUpdatedAt($value)
 * @mixin Eloquent
 */
class TemplatePhoneNumberButton extends Model
{
    protected $table = 'template_phone_number_buttons';

    protected $fillable = [
        'button_component_id',
        'text',
        'phone_number',
    ];

    /**
     * Relationship with the button component.
     */
    public function buttonComponent(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplateButtonComponent::class, 'button_component_id');
    }
}
