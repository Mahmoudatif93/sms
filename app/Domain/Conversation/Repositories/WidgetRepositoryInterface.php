<?php

namespace App\Domain\Conversation\Repositories;

use App\Models\Widget;
use App\Models\Channel;
use App\Models\PreChatForm;
use App\Models\PostChatForm;
use App\Models\Conversation;
use App\Models\LiveChatMessage;

interface WidgetRepositoryInterface
{
    /**
     * Find widget by ID
     */
    public function findById(string $id): ?Widget;

    /**
     * Find widget by ID or fail
     */
    public function findByIdOrFail(string $id): Widget;

    /**
     * Update widget settings
     */
    public function update(Widget $widget, array $data): Widget;

    /**
     * Get channel for widget
     */
    public function getChannelForWidget(string $widgetId): ?Channel;

    /**
     * Get pre-chat form for channel and widget
     */
    public function getPreChatForm(string $channelId, string $widgetId): ?PreChatForm;

    /**
     * Get post-chat form for channel and widget
     */
    public function getPostChatForm(string $channelId, string $widgetId): ?PostChatForm;

    /**
     * Get active conversation for contact
     */
    public function getActiveConversation(string $contactId, string $platform, Channel $channel): ?Conversation;

    /**
     * Check if contact has ended conversations
     */
    public function hasEndedConversations(string $contactId, string $platform, Channel $channel): bool;

    /**
     * Get previous conversations for contact
     */
    public function getPreviousConversations(string $contactId, string $widgetId, int $limit = 5): \Illuminate\Database\Eloquent\Collection;

    /**
     * Get chat history for conversation
     */
    public function getChatHistory(string $conversationId, ?string $beforeId = null, int $limit = 50): \Illuminate\Database\Eloquent\Collection;

    /**
     * Mark messages as read for conversation
     */
    public function markMessagesAsRead(string $conversationId, ?array $messageIds = null): int;

    /**
     * Mark messages as delivered
     */
    public function markMessagesAsDelivered(array $messageIds): int;
}
