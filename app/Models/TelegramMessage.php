<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TelegramMessage extends Model
{
    use HasFactory;

    const MESSAGE_STATUS_INITIATED = 'initiated';
    const MESSAGE_STATUS_SENT = 'sent';
    const MESSAGE_STATUS_DELIVERED = 'delivered';
    const MESSAGE_STATUS_READ = 'read';
    const MESSAGE_STATUS_FAILED = 'failed';
    const MESSAGE_STATUS_DELETED = 'deleted';
    const MESSAGE_STATUS_WARNING = 'warning';
    const SENDER_TYPE_SYSTEM = 'system';

    const MESSAGE_DIRECTION_SENT = 'SENT';
    const MESSAGE_DIRECTION_RECEIVED = 'RECEIVED';

    protected $table = 'telegram_messages';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'conversation_id',
        'telegram_message_id',
        'chat_id',
        'type',
        'content',
        'payload',
        'file_id',
        'file_path',
        'status',
        'from_agent',
        'reply_to_message_id',
    ];

    protected $casts = [
        'payload'    => 'array',
        'from_agent' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->id ??= (string) Str::uuid();
        });
    }

    /* ================== Relations ================== */

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function replyTo()
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
    }

    public function replies()
    {
        return $this->hasMany(self::class, 'reply_to_message_id');
    }

    /* ================== Helpers ================== */

    public function markAsRead(): void
    {
        $this->update(['status' => 'read']);
    }

    public function markAsDelivered(): void
    {
        $this->update(['status' => 'delivered']);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }
}
