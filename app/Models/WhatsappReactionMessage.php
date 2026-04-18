<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 *
 * Represents a WhatsApp reaction message.
 *
 *  This model stores information specifically for reaction messages sent or received through WhatsApp.
 *  It includes details such as the emoji used and the message being reacted to.
 *
 *
 * @property int $id The primary key ID for the reaction message.
 * @property string $message_id The ID of the message being reacted to.
 * @property string|null $emoji The emoji used for the reaction. Null or empty means the reaction was removed.
 * @property int $whatsapp_message_id The ID of the related message in the `whatsapp_messages` table.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WhatsappMessage $whatsappMessage
 * @method static Builder|WhatsappReactionMessage newModelQuery()
 * @method static Builder|WhatsappReactionMessage newQuery()
 * @method static Builder|WhatsappReactionMessage query()
 * @method static Builder|WhatsappReactionMessage whereMessageId($value)
 * @method static Builder|WhatsappReactionMessage whereEmoji($value)
 * @method static Builder|WhatsappReactionMessage whereCreatedAt($value)
 * @method static Builder|WhatsappReactionMessage whereId($value)
 * @method static Builder|WhatsappReactionMessage whereUpdatedAt($value)
 * @method static Builder|WhatsappReactionMessage whereWhatsappMessageId($value)
 * @mixin \Eloquent
 */
class WhatsappReactionMessage extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'whatsapp_reaction_messages';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'message_id',
        'emoji',
        'whatsapp_message_id',
        'direction'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
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
