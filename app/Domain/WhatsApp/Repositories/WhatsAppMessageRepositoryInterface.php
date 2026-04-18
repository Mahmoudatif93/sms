<?php

namespace App\Domain\WhatsApp\Repositories;

use App\Domain\WhatsApp\DTOs\IncomingMessageDTO;
use App\Domain\WhatsApp\DTOs\MessageStatusDTO;
use App\Models\WhatsappConsumerPhoneNumber;
use App\Models\WhatsappMessage;

interface WhatsAppMessageRepositoryInterface
{
    /**
     * Check if message already exists
     */
    public function messageExists(string $messageId): bool;

    /**
     * Find message by ID
     */
    public function findMessage(string $messageId): ?WhatsappMessage;

    /**
     * Find or create consumer phone number
     */
    public function findOrCreateSender(
        string $phoneNumber,
        string $waId,
        string $whatsappBusinessAccountId,
        ?string $name = null
    ): WhatsappConsumerPhoneNumber;

    /**
     * Create WhatsApp message
     */
    public function createMessage(array $data): WhatsappMessage;

    /**
     * Update message status
     */
    public function updateMessageStatus(string $messageId, string $status, ?string $conversationId = null): bool;

    /**
     * Create or update message status record
     */
    public function saveMessageStatus(string $messageId, string $status, string $timestamp): void;

    /**
     * Create message status error
     */
    public function createStatusError(int $messageStatusId, array $errorData): void;

    /**
     * Create text message content
     */
    public function createTextMessage(string $messageId, string $body, ?string $previewUrl = null): object;

    /**
     * Create image message content
     */
    public function createImageMessage(string $messageId, string $mediaId, ?string $caption = null): object;

    /**
     * Create video message content
     */
    public function createVideoMessage(string $messageId, string $mediaId, ?string $caption = null): object;

    /**
     * Create audio message content
     */
    public function createAudioMessage(string $messageId, string $mediaId): object;

    /**
     * Create document message content
     */
    public function createDocumentMessage(
        string $messageId,
        string $mediaId,
        ?string $filename = null,
        ?string $caption = null,
        ?string $link = null
    ): object;

    /**
     * Create reaction message content
     */
    public function createReactionMessage(string $messageId, string $reactedMessageId, ?string $emoji = null): object;

    /**
     * Create interactive message content
     */
    public function createInteractiveMessage(string $messageId, array $interactiveData): object;

    /**
     * Create flow response message content
     */
    public function createFlowResponseMessage(string $messageId, array $flowData): object;

    /**
     * Update messageable relation
     */
    public function updateMessageable(string $messageId, object $messageable): void;

    /**
     * Log meta conversation
     */
    public function logMetaConversation(array $data): void;

    /**
     * Find original message for reaction (with fallback strategies)
     */
    public function findOriginalMessageForReaction(string $messageId, string $conversationId): ?WhatsappMessage;

    public function createStickerMessage(
        string $messageId,
        string $mediaId,
        bool $isAnimated = false,
        ?string $mimeType = null
    ): object;

    /**
     * Create location message content
     */
    public function createLocationMessage(
        string $messageId,
        float $latitude,
        float $longitude,
        ?string $name = null,
        ?string $address = null
    ): object;
}
