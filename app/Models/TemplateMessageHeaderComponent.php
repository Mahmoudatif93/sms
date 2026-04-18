<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Class TemplateMessageHeaderComponent
 *
 * Represents a header component of a WhatsApp template message.
 *
 * @package App\Models
 *
 * @property int $id The primary key.
 * @property int $template_message_id Foreign key to the associated WhatsApp template message.
 * @property string $type Type of header (e.g., image, video, document, text).
 * @property Carbon|null $created_at Timestamp when created.
 * @property Carbon|null $updated_at Timestamp when updated.
 *
 * @property-read WhatsappTemplateMessage|null $templateMessage The related template message.
 *
 * @method static Builder|TemplateMessageHeaderComponent newModelQuery()
 * @method static Builder|TemplateMessageHeaderComponent newQuery()
 * @method static Builder|TemplateMessageHeaderComponent query()
 *
 * @mixin Eloquent
 */
class TemplateMessageHeaderComponent extends Model
{
    protected $table = 'template_message_header_components';

    protected $fillable = [
        'template_message_id',
        'type',
    ];

    /**
     * Get the WhatsApp template message this header belongs to.
     */
    public function templateMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplateMessage::class, 'template_message_id');
    }

    public function headerImageParameter(): HasOne
    {
        return $this->hasOne(TemplateHeaderImageParameter::class, 'tmpl_msg_hdr_component_id');
    }
}
