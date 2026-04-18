<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ChatbotKnowledgeBase extends Model
{
    use HasUuids;

    protected $table = 'chatbot_knowledge_base';

    protected $fillable = [
        'channel_id',
        'category',
        'intent',
        'keywords_text',
        'keywords',
        'question_ar',
        'question_en',
        'answer_ar',
        'answer_en',
        'may_need_handoff',
        'requires_handoff',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'keywords' => 'array',
        'may_need_handoff' => 'boolean',
        'requires_handoff' => 'boolean',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    protected $attributes = [
        'may_need_handoff' => false,
        'requires_handoff' => false,
        'priority' => 0,
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        static::saving(function (ChatbotKnowledgeBase $model) {
            // Sync keywords array to keywords_text for FULLTEXT search
            if (!empty($model->keywords)) {
                $model->keywords_text = implode(' ', $model->keywords);
            }
        });
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForChannel(Builder $query, string $channelId): Builder
    {
        return $query->where('channel_id', $channelId);
    }

    public function scopeFullTextSearch(Builder $query, string $searchTerm, string $language = 'ar'): Builder
    {
        // FULLTEXT index includes all 3 columns (question_ar, question_en, keywords_text)
        // MySQL requires MATCH() to use the exact same columns as the index
        return $query->whereRaw(
            "MATCH(question_ar, question_en, keywords_text) AGAINST(? IN NATURAL LANGUAGE MODE)",
            [$searchTerm]
        )->orderByRaw(
            "MATCH(question_ar, question_en, keywords_text) AGAINST(? IN NATURAL LANGUAGE MODE) DESC",
            [$searchTerm]
        );
    }

    public function scopeByIntent(Builder $query, string $intent): Builder
    {
        return $query->where('intent', $intent);
    }

    public function getQuestion(string $language = 'ar'): ?string
    {
        return $language === 'en' ? $this->question_en : $this->question_ar;
    }

    public function getAnswer(string $language = 'ar'): ?string
    {
        return $language === 'en' ? $this->answer_en : $this->answer_ar;
    }

    public function matchesKeyword(string $message): bool
    {
        if (empty($this->keywords)) {
            return false;
        }

        $messageLower = mb_strtolower($message);

        foreach ($this->keywords as $keyword) {
            if (str_contains($messageLower, mb_strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }
}
