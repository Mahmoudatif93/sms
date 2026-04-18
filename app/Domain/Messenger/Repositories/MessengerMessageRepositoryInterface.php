<?php

namespace App\Domain\Messenger\Repositories;

use App\Models\MessengerConsumer;
use App\Models\MessengerMessage;
use App\Models\MetaPage;

interface MessengerMessageRepositoryInterface
{
    /**
     * Find MetaPage by ID
     */
    public function findMetaPage(string $pageId): ?MetaPage;

    /**
     * Find or create Messenger consumer
     */
    public function findOrCreateConsumer(string $psid, string $metaPageId, ?string $name = null): MessengerConsumer;

    /**
     * Create or update Messenger message
     */
    public function createMessage(array $data): MessengerMessage;

    /**
     * Create text message content
     */
    public function createTextMessage(string $messageId, string $text): object;

    /**
     * Update messageable relation
     */
    public function updateMessageable(string $messageId, object $messageable): void;

    /**
     * Get page access token
     */
    public function getPageAccessToken(MetaPage $metaPage): ?string;

    /**
     * Find consumer by PSID and Page ID
     */
    public function findConsumerByPsid(string $psid, string $metaPageId): ?MessengerConsumer;

    /**
     * Get unread received messages for a conversation
     */
    public function getUnreadReceivedMessages(string $conversationId): \Illuminate\Support\Collection;

    /**
     * Mark message as read
     */
    public function markMessageAsRead(string $messageId): bool;

    /**
     * Get last received message ID for a conversation
     */
    public function getLastReceivedMessageId(string $conversationId): ?string;

    /**
     * Create outgoing message record
     */
    public function createOutgoingMessage(array $data): MessengerMessage;

    /**
     * Find message by ID
     */
    public function findMessage(string $messageId): ?MessengerMessage;

    /**
     * Update message status
     */
    public function updateMessageStatus(string $messageId, string $status): bool;

    /**
     * Get messages sent before a timestamp (for watermark-based status updates)
     *
     * @param string $targetStatus The status we want to update TO (delivered or read)
     */
    public function getMessagesBefore(
        string $pageId,
        string $consumerId,
        int $beforeTimestamp,
        string $direction,
        string $targetStatus
    ): \Illuminate\Support\Collection;

    /**
     * Save message status record
     */
    public function saveMessageStatus(string $messageId, string $status): void;
}
