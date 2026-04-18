<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\WhatsappAuthTemplateSupportedApp
 *
 * @property int $id
 * @property int $button_component_id
 * @property string $package_name
 * @property string $signature_hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WhatsappAuthTemplateButtonComponent $buttonComponent
 * @method static Builder|WhatsappAuthTemplateSupportedApp newModelQuery()
 * @method static Builder|WhatsappAuthTemplateSupportedApp newQuery()
 * @method static Builder|WhatsappAuthTemplateSupportedApp query()
 * @method static Builder|WhatsappAuthTemplateSupportedApp wherePackageName($value)
 * @method static Builder|WhatsappAuthTemplateSupportedApp whereSignatureHash($value)
 * @method static Builder|WhatsappAuthTemplateSupportedApp whereCreatedAt($value)
 * @method static Builder|WhatsappAuthTemplateSupportedApp whereId($value)
 * @method static Builder|WhatsappAuthTemplateSupportedApp whereButtonComponentId($value)
 * @method static Builder|WhatsappAuthTemplateSupportedApp whereUpdatedAt($value)
 * @mixin Eloquent
 */
class WhatsappAuthTemplateSupportedApp extends Model
{
    protected $table = 'whatsapp_auth_template_supported_apps';

    protected $fillable = [
        'button_component_id',
        'package_name',
        'signature_hash',
    ];

    /**
     * Get the button component associated with this supported app.
     */
    public function buttonComponent(): BelongsTo
    {
        return $this->belongsTo(WhatsappAuthTemplateButtonComponent::class, 'button_component_id');
    }
}

