<?php

namespace App\Models;

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
 * @property int $header_component_id
 * @property string $text Text for TEXT headers, supports 1 variable and max 60 characters
 * @property Carbon|null $created_at Creation timestamp
 * @property Carbon|null $updated_at Last update timestamp
 * @property-read WhatsappMessageTemplate $template
 * @method static Builder|TemplateHeaderTextComponent newModelQuery()
 * @method static Builder|TemplateHeaderTextComponent newQuery()
 * @method static Builder|TemplateHeaderTextComponent query()
 * @method static Builder|TemplateHeaderTextComponent whereCreatedAt($value)
 * @method static Builder|TemplateHeaderTextComponent whereHeaderComponentId($value)
 * @method static Builder|TemplateHeaderTextComponent whereId($value)
 * @method static Builder|TemplateHeaderTextComponent whereText($value)
 * @method static Builder|TemplateHeaderTextComponent whereUpdatedAt($value)
 * @mixin Eloquent
 */
class TemplateHeaderTextComponent extends Model
{
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'template_header_text_components';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'header_component_id',
        'text',
    ];

    /**
     * Get the template that owns the header text component.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplateHeaderComponent::class, 'header_component_id');
    }

    /**
     * Get the text examples associated with the header text.
     */
    public function textExample(): HasOne
    {
        return $this->hasOne(TemplateHeaderTextExample::class, 'header_text_component_id');
    }

    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];
}
