<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotMessage extends Model
{
    use HasUuids;

    protected $table = 'chatbot_messages';

    public $timestamps = false;

    protected $fillable = [
        'chatbot_conversation_id',
        'user_message',
        'bot_response',
        'knowledge_base_id',
        'confidence_score',
        'used_ai',
        'tokens_used',
        'cost_usd',
        'language',
        'created_at',
    ];

    protected $casts = [
        'confidence_score' => 'float',
        'used_ai' => 'boolean',
        'tokens_used' => 'integer',
        'cost_usd' => 'float',
        'created_at' => 'datetime',
    ];

    protected $attributes = [
        'used_ai' => false,
        'language' => 'ar',
    ];

    protected static function booted(): void
    {
        static::creating(function (ChatbotMessage $model) {
            if (!$model->created_at) {
                $model->created_at = now();
            }
        });
    }

    public function chatbotConversation(): BelongsTo
    {
        return $this->belongsTo(ChatbotConversation::class);
    }

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(ChatbotKnowledgeBase::class, 'knowledge_base_id');
    }

    public function wasAnsweredFromKnowledge(): bool
    {
        return !$this->used_ai && $this->knowledge_base_id !== null;
    }

    public function wasAnsweredByAI(): bool
    {
        return $this->used_ai;
    }
}
