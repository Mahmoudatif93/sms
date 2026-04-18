<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\MessengerTextMessage
 *
 * @property int $id
 * @property string $messenger_message_id
 * @property string $text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read MessengerMessage $messengerMessage
 *
 * @method static Builder|MessengerTextMessage newModelQuery()
 * @method static Builder|MessengerTextMessage newQuery()
 * @method static Builder|MessengerTextMessage query()
 * @method static Builder|MessengerTextMessage whereId($value)
 * @method static Builder|MessengerTextMessage whereMessengerMessageId($value)
 * @method static Builder|MessengerTextMessage whereText($value)
 * @method static Builder|MessengerTextMessage whereCreatedAt($value)
 * @method static Builder|MessengerTextMessage whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class MessengerTextMessage extends Model
{
    protected $table = 'messenger_text_messages';

    protected $fillable = [
        'messenger_message_id',
        'text',
    ];

    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public function messengerMessage(): BelongsTo
    {
        return $this->belongsTo(MessengerMessage::class, 'messenger_message_id');
    }
}
