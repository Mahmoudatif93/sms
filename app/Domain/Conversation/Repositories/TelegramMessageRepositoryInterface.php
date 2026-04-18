<?php

namespace App\Domain\Conversation\Repositories;

use App\Models\TelegramMessage;

interface TelegramMessageRepositoryInterface
{
    /**
     * Create a telegram message
     */
    public function create(array $data): TelegramMessage;

    /**
     * Update telegram message
     */
    public function update(TelegramMessage $message, array $data): bool;

    /**
     * Find message by ID
     */
    public function findById(string $id): ?TelegramMessage;

    /**
     * Find message by ID within conversation
     */
    public function findByIdInConversation(string $id, string $conversationId): ?TelegramMessage;

    /**
     * Create text message
     */
    public function createTextMessage(string $conversationId, string $chatId, string $text, bool $fromAgent = false, ?string $replyToMessageId = null): TelegramMessage;

    /**
     * Create file/media message
     */
    public function createFileMessage(string $conversationId, string $chatId, string $type, ?string $caption = null, ?string $fileId = null, ?string $filePath = null, bool $fromAgent = false, ?string $replyToMessageId = null): TelegramMessage;

    /**
     * Create location message
     */
    public function createLocationMessage(string $conversationId, string $chatId, float $latitude, float $longitude, ?string $name = null, ?string $address = null, bool $fromAgent = false, ?string $replyToMessageId = null): TelegramMessage;

    /**
     * Create or update reaction
     */
    public function upsertReaction(string $messageId, string $emoji, string $direction): TelegramMessage;

    /**
     * Delete reaction
     */
    public function deleteReaction(string $messageId, string $direction): bool;

    /**
     * Save message status
     */
    public function saveMessageStatus(string $messageId, string $status): void;

    /**
     * Get telegram configuration by connector
     */
    public function getTelegramConfiguration(string $connectorId): mixed;

    /**
     * Create message for conversation
     */
    public function createForConversation(string $conversationId, array $data): TelegramMessage;
}
