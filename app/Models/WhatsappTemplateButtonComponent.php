<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 
 *
 * @property int $id
 * @property int $template_id
 * @property string $type Type of button component
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\WhatsappMessageTemplate $template
 * @method static \Illuminate\Database\Eloquent\Builder|WhatsappTemplateButtonComponent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WhatsappTemplateButtonComponent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WhatsappTemplateButtonComponent query()
 * @method static \Illuminate\Database\Eloquent\Builder|WhatsappTemplateButtonComponent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhatsappTemplateButtonComponent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhatsappTemplateButtonComponent whereTemplateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhatsappTemplateButtonComponent whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhatsappTemplateButtonComponent whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class WhatsappTemplateButtonComponent extends Model
{
    protected $table = 'whatsapp_template_button_components';

    protected $fillable = [
        'template_id',
        'type',
    ];

    /**
     * Define the relationship with the WhatsappMessageTemplate.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessageTemplate::class, 'template_id');
    }
}
