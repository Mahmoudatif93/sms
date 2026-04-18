<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * App\Models\WhatsappAuthTemplateButtonComponent
 *
 * @property int $id
 * @property int $template_id
 * @property string $otp_type
 * @property string|null $text
 * @property string|null $autofill_text
 * @property bool|null $zero_tap_terms_accepted
 * @property array|null $supported_apps
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WhatsappMessageTemplate $template
 * @method static Builder|WhatsappAuthTemplateButtonComponent newModelQuery()
 * @method static Builder|WhatsappAuthTemplateButtonComponent newQuery()
 * @method static Builder|WhatsappAuthTemplateButtonComponent query()
 * @method static Builder|WhatsappAuthTemplateButtonComponent whereOtpType($value)
 * @method static Builder|WhatsappAuthTemplateButtonComponent whereText($value)
 * @method static Builder|WhatsappAuthTemplateButtonComponent whereAutofillText($value)
 * @method static Builder|WhatsappAuthTemplateButtonComponent whereZeroTapTermsAccepted($value)
 * @method static Builder|WhatsappAuthTemplateButtonComponent whereSupportedApps($value)
 * @method static Builder|WhatsappAuthTemplateButtonComponent whereCreatedAt($value)
 * @method static Builder|WhatsappAuthTemplateButtonComponent whereId($value)
 * @method static Builder|WhatsappAuthTemplateButtonComponent whereTemplateId($value)
 * @method static Builder|WhatsappAuthTemplateButtonComponent whereUpdatedAt($value)
 * @mixin  Eloquent
 */
class WhatsappAuthTemplateButtonComponent extends Model
{
    protected $table = 'whatsapp_auth_template_button_components';

    protected $fillable = [
        'template_id',
        'otp_type',
        'text',
        'autofill_text',
        'zero_tap_terms_accepted'
    ];

    /**
     * Get the template associated with the authentication button component.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessageTemplate::class, 'template_id');
    }

    public function supportedApps(): HasMany
    {
        return $this->hasMany(WhatsappAuthTemplateSupportedApp::class, 'button_component_id');
    }


}
