<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 *
 * Represents a WhatsApp text message.
 *
 *  This model stores information specifically for text messages sent or received through WhatsApp.
 *  It includes details such as the message body and whether a URL preview should be included.
 *
 *
 * @property int $id The primary key ID for the text message.
 * @property string $body The text of the message. Must start with 'http://' or 'https://' if containing URLs.
 * @property bool $preview_url Indicates whether to attempt rendering a link preview of the first URL in the body.
 * @property int $whatsapp_message_id The ID of the related message in the `whatsapp_messages` table.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WhatsappMessage $whatsappMessage
 * @method static Builder|WhatsappTextMessage newModelQuery()
 * @method static Builder|WhatsappTextMessage newQuery()
 * @method static Builder|WhatsappTextMessage query()
 * @method static Builder|WhatsappTextMessage whereBody($value)
 * @method static Builder|WhatsappTextMessage whereCreatedAt($value)
 * @method static Builder|WhatsappTextMessage whereId($value)
 * @method static Builder|WhatsappTextMessage wherePreviewUrl($value)
 * @method static Builder|WhatsappTextMessage whereUpdatedAt($value)
 * @method static Builder|WhatsappTextMessage whereWhatsappMessageId($value)
 * @mixin \Eloquent
 */
class WhatsappTextMessage extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'whatsapp_text_messages';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'body',
        'preview_url',
        'whatsapp_message_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'preview_url' => 'boolean',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];

    /**
     * Get the parent WhatsappMessage model.
     *
     * @return MorphOne
     */
    public function message(): MorphOne
    {
        return $this->morphOne(WhatsappMessage::class, 'messageable');
    }
}
