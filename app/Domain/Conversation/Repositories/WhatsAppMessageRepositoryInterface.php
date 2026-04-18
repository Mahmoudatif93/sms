<?php

namespace App\Domain\Conversation\Repositories;

use App\Models\WhatsappConsumerPhoneNumber;
use App\Models\WhatsappMessage;

interface WhatsAppMessageRepositoryInterface
{
    /**
     * Create a WhatsApp message
     */
    public function create(array $data): WhatsappMessage;

    /**
     * Update a WhatsApp message
     */
    public function update(WhatsappMessage $message, array $data): bool;

    /**
     * Find message by ID
     */
    public function findById(string $id): ?WhatsappMessage;

    /**
     * Get or create consumer phone number
     */
    public function findOrCreateConsumer(string $phoneNumber, string $businessAccountId, ?string $waId = null): WhatsappConsumerPhoneNumber;

    /**
     * Create text message content
     */
    public function createTextMessage(string $messageId, string $body, ?bool $previewUrl = null): mixed;

    /**
     * Create image message content
     */
    public function createImageMessage(string $messageId, ?string $mediaId, ?string $caption = null): mixed;

    /**
     * Create video message content
     */
    public function createVideoMessage(string $messageId, ?string $mediaId, ?string $caption = null): mixed;

    /**
     * Create audio message content
     */
    public function createAudioMessage(string $messageId, ?string $mediaId): mixed;

    /**
     * Create document message content
     */
    public function createDocumentMessage(string $messageId, ?string $mediaId, ?string $filename = null, ?string $caption = null): mixed;

    /**
     * Create location message content
     */
    public function createLocationMessage(string $messageId, float $latitude, float $longitude, ?string $name = null, ?string $address = null): mixed;

    /**
     * Create reaction message content
     */
    public function createReactionMessage(string $messageId, string $reactedMessageId, string $emoji, string $direction): mixed;

    /**
     * Delete reaction by the reacted message ID
     */
    public function deleteReactionByMessageId(string $messageId): bool;

    /**
     * Save message status
     */
    public function saveMessageStatus(string $messageId, string $status): void;

    /**
     * Create message translation record
     */
    public function createTranslation(string $messageId, string $messageType, string $translatedText, ?string $targetLanguage): void;
}
