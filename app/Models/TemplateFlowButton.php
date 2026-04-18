<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class TemplateFlowButton
 *
 * @property int $id
 * @property int $button_component_id
 * @property string $text
 * @property string|null $flow_id
 * @property string|null $flow_json
 * @property string $flow_action
 * @property string|null $navigate_screen
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @mixin Eloquent
 */
class TemplateFlowButton extends Model
{
    protected $table = 'template_flow_buttons';

    protected $fillable = [
        'button_component_id',
        'text',
        'flow_id',
        'flow_json',
        'flow_action',
        'navigate_screen',
    ];

    /**
     * Get the associated button component.
     *
     * @return BelongsTo
     */
    public function buttonComponent(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplateButtonComponent::class, 'button_component_id');
    }
}
