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
 * @property int $header_text_component_id
 * @property string $header_text Example value for the TEXT header variable
 * @property Carbon|null $created_at Creation timestamp
 * @property Carbon|null $updated_at Last update timestamp
 * @property-read WhatsappTemplateHeaderComponent $headerTextComponent
 * @method static Builder|TemplateHeaderTextExample newModelQuery()
 * @method static Builder|TemplateHeaderTextExample newQuery()
 * @method static Builder|TemplateHeaderTextExample query()
 * @method static Builder|TemplateHeaderTextExample whereCreatedAt($value)
 * @method static Builder|TemplateHeaderTextExample whereHeaderText($value)
 * @method static Builder|TemplateHeaderTextExample whereHeaderTextComponentId($value)
 * @method static Builder|TemplateHeaderTextExample whereId($value)
 * @method static Builder|TemplateHeaderTextExample whereUpdatedAt($value)
 * @mixin Eloquent
 */
class TemplateHeaderTextExample extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'template_header_text_examples';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'header_text_component_id',
        'header_text',
        'created_at',
        'updated_at',
    ];


    /**
     * Get the header text component that owns the example.
     */
    public function headerTextComponent(): BelongsTo
    {
        return $this->belongsTo(TemplateHeaderTextComponent::class, 'header_text_component_id');
    }

    /**
     * Get the text examples associated with the header text.
     */
    public function textExamples(): HasOne
    {
        return $this->hasOne(TemplateHeaderTextExample::class, 'header_text_component_id');
    }
}
