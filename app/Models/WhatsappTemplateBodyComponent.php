<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property int $id
 * @property int $template_id
 * @property string $text Body text for the template
 * @property Carbon|null $created_at Creation timestamp
 * @property Carbon|null $updated_at Last update timestamp
 * @property-read WhatsappMessageTemplate $template
 * @method static Builder|WhatsappTemplateBodyComponent newModelQuery()
 * @method static Builder|WhatsappTemplateBodyComponent newQuery()
 * @method static Builder|WhatsappTemplateBodyComponent query()
 * @method static Builder|WhatsappTemplateBodyComponent whereCreatedAt($value)
 * @method static Builder|WhatsappTemplateBodyComponent whereId($value)
 * @method static Builder|WhatsappTemplateBodyComponent whereTemplateId($value)
 * @method static Builder|WhatsappTemplateBodyComponent whereText($value)
 * @method static Builder|WhatsappTemplateBodyComponent whereUpdatedAt($value)
 * @mixin Eloquent
 */
class WhatsappTemplateBodyComponent extends Model
{
    protected $table = 'whatsapp_template_body_components';

    protected $fillable = [
        'template_id',
        'text'
    ];


    /**
     * Get the template associated with the body component.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessageTemplate::class, 'template_id');
    }

    /**
     * Get the text examples associated with the body text.
     */
    public function textExamples(): HasMany
    {
        return $this->hasMany(TemplateBodyTextExample::class, 'body_text_component_id');
    }

}
