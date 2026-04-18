<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $header_component_id
 * @property string $header_handle Media Example Header Handle
 * @property int|null $created_at Creation timestamp
 * @property int|null $updated_at Last update timestamp
 * @property-read WhatsappTemplateHeaderComponent $whatsappTemplateHeaderComponent
 * @method static Builder|TemplateHeaderMediaComponent newModelQuery()
 * @method static Builder|TemplateHeaderMediaComponent newQuery()
 * @method static Builder|TemplateHeaderMediaComponent query()
 * @method static Builder|TemplateHeaderMediaComponent whereCreatedAt($value)
 * @method static Builder|TemplateHeaderMediaComponent whereHeaderComponentId($value)
 * @method static Builder|TemplateHeaderMediaComponent whereHeaderHandle($value)
 * @method static Builder|TemplateHeaderMediaComponent whereId($value)
 * @method static Builder|TemplateHeaderMediaComponent whereUpdatedAt($value)
 * @mixin Eloquent
 */
class TemplateHeaderMediaComponent extends Model
{
    // Define the table associated with the model (optional if the name is conventional)
    protected $table = 'template_header_media_components';

    // Allow mass assignment for these fields
    protected $fillable = [
        'header_component_id',
        'header_handle',
    ];

    // Define the relationship to the WhatsappTemplateHeaderComponent
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public function whatsappTemplateHeaderComponent(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplateHeaderComponent::class, 'header_component_id');
    }
}
