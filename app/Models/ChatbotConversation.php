<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatbotConversation extends Model
{
    use HasUuids;

    protected $table = 'chatbot_conversations';

    protected $fillable = [
        'conversation_id',
        'channel_id',
        'is_bot_active',
        'failed_attempts',
        'handoff_reason',
        'handoff_at',
    ];

    protected $casts = [
        'is_bot_active' => 'boolean',
        'failed_attempts' => 'integer',
        'handoff_at' => 'datetime',
    ];

    protected $attributes = [
        'is_bot_active' => true,
        'failed_attempts' => 0,
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatbotMessage::class);
    }

    public function incrementFailedAttempts(): int
    {
        $this->increment('failed_attempts');
        return $this->failed_attempts;
    }

    public function resetFailedAttempts(): void
    {
        $this->update(['failed_attempts' => 0]);
    }

    public function deactivateBot(string $reason): void
    {
        $this->update([
            'is_bot_active' => false,
            'handoff_reason' => $reason,
            'handoff_at' => now(),
        ]);
    }

    public function reactivateBot(): void
    {
        $this->update([
            'is_bot_active' => true,
            'failed_attempts' => 0,
            'handoff_reason' => null,
            'handoff_at' => null,
        ]);
    }

    public function shouldHandoff(int $threshold): bool
    {
        return $this->failed_attempts >= $threshold;
    }
}
