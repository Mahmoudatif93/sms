<?php

namespace App\Domain\Chatbot\Repositories;

use App\Models\Channel;
use App\Models\ChatbotConversation;
use App\Models\ChatbotKnowledgeBase;
use App\Models\ChatbotMessage;
use App\Models\ChatbotSettings;
use App\Models\Conversation;
use Illuminate\Support\Collection;

interface ChatbotRepositoryInterface
{
    // Settings
    public function getSettings(string $channelId): ?ChatbotSettings;
    public function createOrUpdateSettings(string $channelId, array $data): ChatbotSettings;

    // Knowledge Base
    public function getKnowledge(string $channelId, bool $activeOnly = true): Collection;
    public function getAllKnowledge(string $channelId): Collection;
    public function searchKnowledge(string $channelId, string $query, string $language): Collection;
    public function findKnowledgeByIntent(string $channelId, string $intent): ?ChatbotKnowledgeBase;
    public function createKnowledge(array $data): ChatbotKnowledgeBase;
    public function updateKnowledge(string $id, array $data): ChatbotKnowledgeBase;
    public function deleteKnowledge(string $id): bool;
    public function importKnowledge(string $channelId, array $items): array;

    // Chatbot Conversations
    public function getOrCreateChatbotConversation(Conversation $conversation): ChatbotConversation;
    public function updateChatbotConversation(string $id, array $data): ChatbotConversation;

    // Messages
    public function createMessage(array $data): ChatbotMessage;
    public function getConversationMessages(string $chatbotConversationId, int $limit = 10): Collection;
}
