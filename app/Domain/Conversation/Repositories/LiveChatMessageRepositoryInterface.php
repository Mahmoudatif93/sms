<?php

namespace App\Domain\Conversation\Repositories;

use App\Models\LiveChatMessage;

interface LiveChatMessageRepositoryInterface
{
    /**
     * Create a LiveChat message
     */
    public function create(array $data): LiveChatMessage;

    /**
     * Update a LiveChat message
     */
    public function update(LiveChatMessage $message, array $data): bool;

    /**
     * Find message by ID
     */
    public function findById(string $id): ?LiveChatMessage;

    /**
     * Find message by ID within a conversation
     */
    public function findByIdInConversation(string $id, string $conversationId): ?LiveChatMessage;

    /**
     * Create text message content
     */
    public function createTextMessage(string $text): mixed;

    /**
     * Create file message content
     */
    public function createFileMessage(?string $caption = null): mixed;

    /**
     * Create or update reaction
     */
    public function upsertReaction(string $messageId, string $emoji, string $direction): mixed;

    /**
     * Delete reaction by message ID and direction
     */
    public function deleteReaction(string $messageId, string $direction): bool;

    /**
     * Save message status
     */
    public function saveMessageStatus(string $messageId, string $status): void;

    /**
     * Get LiveChat configuration by connector ID
     */
    public function getLiveChatConfiguration(string $connectorId): mixed;

    /**
     * Create a message for a conversation
     */
    public function createForConversation(string $conversationId, array $data): LiveChatMessage;
}
