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
 * @property int $template_id
 * @property string $text Footer text for the template (max 60 characters)
 * @property Carbon|null $created_at Creation timestamp
 * @property Carbon|null $updated_at Last update timestamp
 * @property-read WhatsappMessageTemplate $template
 * @method static Builder|WhatsappTemplateFooterComponent newModelQuery()
 * @method static Builder|WhatsappTemplateFooterComponent newQuery()
 * @method static Builder|WhatsappTemplateFooterComponent query()
 * @method static Builder|WhatsappTemplateFooterComponent whereCreatedAt($value)
 * @method static Builder|WhatsappTemplateFooterComponent whereId($value)
 * @method static Builder|WhatsappTemplateFooterComponent whereTemplateId($value)
 * @method static Builder|WhatsappTemplateFooterComponent whereText($value)
 * @method static Builder|WhatsappTemplateFooterComponent whereUpdatedAt($value)
 * @mixin Eloquent
 */
class WhatsappTemplateFooterComponent extends Model
{
    protected $table = 'whatsapp_template_footer_components';

    protected $fillable = [
        'template_id',
        'text',
    ];

    // Define relationship with the WhatsappMessageTemplate model
    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessageTemplate::class, 'template_id');
    }
}
