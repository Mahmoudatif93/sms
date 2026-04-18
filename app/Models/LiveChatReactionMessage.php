<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\LiveChatReactionMessage
 *
 * @property int $id
 * @property string $livechat_message_id
 * @property string $emoji
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read LiveChatMessage $livechatMessage
 */
class LiveChatReactionMessage extends Model
{
    protected $table = 'livechat_reaction_messages';

    protected $fillable = [
        'livechat_message_id',
        'emoji',
        'direction'
    ];

    /**
     * Get the message that this reaction belongs to.
     */
    public function livechatMessage(): BelongsTo
    {
        return $this->belongsTo(LiveChatMessage::class, 'livechat_message_id');
    }
}
