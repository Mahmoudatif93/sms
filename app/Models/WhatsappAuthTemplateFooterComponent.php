<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\WhatsappAuthTemplateFooterComponent
 *
 * @property int $id
 * @property int $template_id
 * @property int|null $code_expiration_minutes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WhatsappMessageTemplate $template
 * @method static Builder|WhatsappAuthTemplateFooterComponent newModelQuery()
 * @method static Builder|WhatsappAuthTemplateFooterComponent newQuery()
 * @method static Builder|WhatsappAuthTemplateFooterComponent query()
 * @method static Builder|WhatsappAuthTemplateFooterComponent whereCodeExpirationMinutes($value)
 * @method static Builder|WhatsappAuthTemplateFooterComponent whereCreatedAt($value)
 * @method static Builder|WhatsappAuthTemplateFooterComponent whereId($value)
 * @method static Builder|WhatsappAuthTemplateFooterComponent whereTemplateId($value)
 * @method static Builder|WhatsappAuthTemplateFooterComponent whereUpdatedAt($value)
 * @mixin Eloquent
 */
class WhatsappAuthTemplateFooterComponent extends Model
{
    protected $table = 'whatsapp_auth_template_footer_components';

    protected $fillable = [
        'template_id',
        'code_expiration_minutes',
    ];

    /**
     * Get the template associated with the authentication footer component.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessageTemplate::class, 'template_id');
    }
}
