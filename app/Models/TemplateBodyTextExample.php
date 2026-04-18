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
 * @property int $body_text_component_id
 * @property string $body_text Example value for the body text variable
 * @property Carbon|null $created_at Creation timestamp
 * @property Carbon|null $updated_at Last update timestamp
 * @property-read WhatsappTemplateBodyComponent $bodyTextComponent
 * @method static Builder|TemplateBodyTextExample newModelQuery()
 * @method static Builder|TemplateBodyTextExample newQuery()
 * @method static Builder|TemplateBodyTextExample query()
 * @method static Builder|TemplateBodyTextExample whereBodyText($value)
 * @method static Builder|TemplateBodyTextExample whereBodyTextComponentId($value)
 * @method static Builder|TemplateBodyTextExample whereCreatedAt($value)
 * @method static Builder|TemplateBodyTextExample whereId($value)
 * @method static Builder|TemplateBodyTextExample whereUpdatedAt($value)
 * @mixin Eloquent
 */
class TemplateBodyTextExample extends Model
{
    protected $table = 'template_body_text_examples';

    protected $fillable = [
        'body_text_component_id',
        'body_text',
    ];

    /**
     * Get the body text component associated with this example.
     */
    public function bodyTextComponent(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplateBodyComponent::class, 'body_text_component_id');
    }
}
