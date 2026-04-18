<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


/**
 * App\Models\TemplateBodyTextParameter
 *
 * Represents a text parameter within a WhatsApp template message body component.
 *
 * @property int $id The primary key of the text parameter.
 * @property int $template_message_body_component_id Foreign key to the body component that this text parameter belongs to.
 * @property string $text The text content for this parameter, with a character limit based on its component type.
 * @property int|null $created_at The timestamp when the text parameter was created.
 * @property int|null $updated_at The timestamp when the text parameter was last updated.
 *
 * @property-read TemplateMessageBodyComponent|null $bodyComponent The body component associated with this text parameter.
 *
 * @method static Builder|TemplateBodyTextParameter newModelQuery() Begin a new model query.
 * @method static Builder|TemplateBodyTextParameter newQuery() Begin a new query for this model.
 * @method static Builder|TemplateBodyTextParameter query() Get a new query builder for this model.
 *
 * @mixin Eloquent
 */
class TemplateBodyTextParameter extends Model
{
    protected $table = 'template_body_text_parameters';

    protected $fillable = [
        'template_message_body_component_id',
        'text',
    ];

    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    /**
     * Get the body component associated with this text parameter.
     */
    public function bodyComponent(): BelongsTo
    {
        return $this->belongsTo(TemplateMessageBodyComponent::class, 'template_message_body_component_id');
    }
}
