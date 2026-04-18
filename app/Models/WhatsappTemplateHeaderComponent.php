<?php

namespace App\Models;

use App\Http\Whatsapp\WhatsappTemplatesComponents\HeaderLocationComponent;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property int $id
 * @property int $template_id
 * @property string|null $format
 * @property Carbon|null $created_at Creation timestamp
 * @property Carbon|null $updated_at Last update timestamp
 * @property-read WhatsappMessageTemplate $template
 * @property-read TemplateHeaderTextExample|null $textExamples
 * @method static Builder|WhatsappTemplateHeaderComponent newModelQuery()
 * @method static Builder|WhatsappTemplateHeaderComponent newQuery()
 * @method static Builder|WhatsappTemplateHeaderComponent query()
 * @method static Builder|WhatsappTemplateHeaderComponent whereCreatedAt($value)
 * @method static Builder|WhatsappTemplateHeaderComponent whereFormat($value)
 * @method static Builder|WhatsappTemplateHeaderComponent whereId($value)
 * @method static Builder|WhatsappTemplateHeaderComponent whereTemplateId($value)
 * @method static Builder|WhatsappTemplateHeaderComponent whereUpdatedAt($value)
 * @mixin Eloquent
 */
class WhatsappTemplateHeaderComponent extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'whatsapp_template_header_components';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'template_id',
        'format',
        'created_at',
        'updated_at'
    ];

    /**
     * Cast attributes to specific data types.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * Get the template that owns the header component.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessageTemplate::class, 'template_id');
    }

    /**
     * Get the text component associated with the header.
     */
    public function textComponent(): HasOne
    {
        return $this->hasOne(TemplateHeaderTextComponent::class, 'header_component_id');
    }

    public function mediaComponent(): HasOne
    {
        return $this->hasOne(TemplateHeaderMediaComponent::class, 'header_component_id');
    }

    public function locationComponent(): HasOne
    {
        return $this->hasOne(HeaderLocationComponent::class, 'header_component_id');
    }
}
