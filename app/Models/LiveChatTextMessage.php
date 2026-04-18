<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
/**
 * App\Models\LiveChatTextMessage
 *
 * @property string $id
 * @property string $text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read LiveChatMessage $message
 */
class LiveChatTextMessage extends Model
{
    use SoftDeletes,HasUuids;

    protected $table = 'livechat_text_messages';

    protected $fillable = [
        'text'
    ];

    /**
     * Get the message that owns this text message.
     */
    public function message(): MorphOne
    {
        return $this->morphOne(LiveChatMessage::class, 'messageable');
    }
}
