<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class TemplateCopyCodeButton
 *
 * @property int $id
 * @property int $button_component_id
 * @property string $example
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @mixin Eloquent
 */
class TemplateCopyCodeButton extends Model
{
    protected $table = 'template_copy_code_buttons';

    protected $fillable = [
        'button_component_id',
        'example',
    ];

    public function buttonComponent(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplateButtonComponent::class, 'button_component_id');
    }
}
