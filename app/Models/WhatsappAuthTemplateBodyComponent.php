<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\WhatsappAuthTemplateBodyComponent
 *
 * @property int $id
 * @property int $template_id
 * @property bool|null $add_security_recommendation
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WhatsappMessageTemplate $template
 * @method static Builder|WhatsappAuthTemplateBodyComponent newModelQuery()
 * @method static Builder|WhatsappAuthTemplateBodyComponent newQuery()
 * @method static Builder|WhatsappAuthTemplateBodyComponent query()
 * @method static Builder|WhatsappAuthTemplateBodyComponent whereAddSecurityRecommendation($value)
 * @method static Builder|WhatsappAuthTemplateBodyComponent whereCreatedAt($value)
 * @method static Builder|WhatsappAuthTemplateBodyComponent whereId($value)
 * @method static Builder|WhatsappAuthTemplateBodyComponent whereTemplateId($value)
 * @method static Builder|WhatsappAuthTemplateBodyComponent whereUpdatedAt($value)
 * @mixin Eloquent
 */
class WhatsappAuthTemplateBodyComponent extends Model
{
    protected $table = 'whatsapp_auth_template_body_components';

    protected $fillable = [
        'template_id',
        'add_security_recommendation',
    ];

    /**
     * Get the template associated with the authentication body component.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessageTemplate::class, 'template_id');
    }
}
