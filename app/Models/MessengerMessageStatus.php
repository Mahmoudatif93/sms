<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents the status of a Messenger message.
 *
 * @property int $id
 * @property string $messenger_message_id
 * @property string $status
 * @property int $timestamp
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read MessengerMessage $messengerMessage
 */
class MessengerMessageStatus extends Model
{
    protected $table = 'messenger_message_statuses';

    protected $fillable = [
        'messenger_message_id',
        'status',
        'timestamp',
    ];

    protected $casts = [
        'timestamp' => 'integer',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    /**
     * Get the Messenger message associated with this status.
     */
    public function messengerMessage(): BelongsTo
    {
        return $this->belongsTo(MessengerMessage::class, 'messenger_message_id', 'id');
    }
}
