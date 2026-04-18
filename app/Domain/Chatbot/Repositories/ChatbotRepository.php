<?php

namespace App\Domain\Chatbot\Repositories;

use App\Models\ChatbotConversation;
use App\Models\ChatbotKnowledgeBase;
use App\Models\ChatbotMessage;
use App\Models\ChatbotSettings;
use App\Models\Conversation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ChatbotRepository implements ChatbotRepositoryInterface
{
    // ========================================
    // Settings
    // ========================================

    public function getSettings(string $channelId): ?ChatbotSettings
    {
        return ChatbotSettings::where('channel_id', $channelId)->first();
    }

    public function createOrUpdateSettings(string $channelId, array $data): ChatbotSettings
    {
        return ChatbotSettings::updateOrCreate(
            ['channel_id' => $channelId],
            $data
        );
    }

    // ========================================
    // Knowledge Base
    // ========================================

    public function getKnowledge(string $channelId, bool $activeOnly = true): Collection
    {
        $query = ChatbotKnowledgeBase::where('channel_id', $channelId);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getAllKnowledge(string $channelId): Collection
    {
        return ChatbotKnowledgeBase::where('channel_id', $channelId)
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();
    }

    public function searchKnowledge(string $channelId, string $query, string $language): Collection
    {
        return ChatbotKnowledgeBase::forChannel($channelId)
            ->active()
            ->fullTextSearch($query, $language)
            ->limit(5)
            ->get();
    }

    public function findKnowledgeByIntent(string $channelId, string $intent): ?ChatbotKnowledgeBase
    {
        return ChatbotKnowledgeBase::forChannel($channelId)
            ->active()
            ->byIntent($intent)
            ->first();
    }

    public function createKnowledge(array $data): ChatbotKnowledgeBase
    {
        return ChatbotKnowledgeBase::create($data);
    }

    public function updateKnowledge(string $id, array $data): ChatbotKnowledgeBase
    {
        $knowledge = ChatbotKnowledgeBase::findOrFail($id);
        $knowledge->update($data);
        return $knowledge->fresh();
    }

    public function deleteKnowledge(string $id): bool
    {
        return ChatbotKnowledgeBase::where('id', $id)->delete() > 0;
    }

    public function importKnowledge(string $channelId, array $items): array
    {
        $imported = 0;
        $updated = 0;
        $errors = [];

        foreach ($items as $index => $item) {
            try {
                $item['channel_id'] = $channelId;

                $existing = ChatbotKnowledgeBase::where('channel_id', $channelId)
                    ->where('intent', $item['intent'] ?? Str::uuid()->toString())
                    ->first();

                if ($existing) {
                    $existing->update($item);
                    $updated++;
                } else {
                    ChatbotKnowledgeBase::create($item);
                    $imported++;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'intent' => $item['intent'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    // ========================================
    // Chatbot Conversations
    // ========================================

    public function getOrCreateChatbotConversation(Conversation $conversation): ChatbotConversation
    {
        return ChatbotConversation::firstOrCreate(
            ['conversation_id' => $conversation->id],
            [
                'channel_id' => $conversation->channel_id,
                'is_bot_active' => true,
                'failed_attempts' => 0,
            ]
        );
    }

    public function updateChatbotConversation(string $id, array $data): ChatbotConversation
    {
        $chatbotConversation = ChatbotConversation::findOrFail($id);
        $chatbotConversation->update($data);
        return $chatbotConversation->fresh();
    }

    // ========================================
    // Messages
    // ========================================

    public function createMessage(array $data): ChatbotMessage
    {
        return ChatbotMessage::create($data);
    }

    public function getConversationMessages(string $chatbotConversationId, int $limit = 10): Collection
    {
        return ChatbotMessage::where('chatbot_conversation_id', $chatbotConversationId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }
}
