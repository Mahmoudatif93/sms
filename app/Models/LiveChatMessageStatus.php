<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveChatMessageStatus extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */

    protected $table = 'livechat_message_statuses';

    protected $fillable = [
        'livechat_message_id',
        'status',
        'timestamp',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'timestamp' => 'integer',
        'status' => 'string',
    ];

    /**
     * Get the message that owns this status.
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(LivechatMessage::class, 'livechat_message_id');
    }
}
