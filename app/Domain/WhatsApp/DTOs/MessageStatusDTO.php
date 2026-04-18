<?php

namespace App\Domain\WhatsApp\DTOs;

class MessageStatusDTO
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $status,
        public readonly string $timestamp,
        public readonly string $phoneNumberId,
        public readonly ?string $recipientId = null,
        public readonly ?string $conversationId = null,
        public readonly ?string $conversationType = null,
        public readonly ?string $expirationTimestamp = null,
        public readonly ?string $pricingCategory = null,
        public readonly array $errors = [],
    ) {}

    public static function fromWebhookData(array $status, string $phoneNumberId): self
    {
        return new self(
            messageId: $status['id'],
            status: $status['status'],
            timestamp: $status['timestamp'],
            phoneNumberId: $phoneNumberId,
            recipientId: $status['recipient_id'] ?? null,
            conversationId: $status['conversation']['id'] ?? null,
            conversationType: $status['conversation']['origin']['type'] ?? null,
            expirationTimestamp: $status['conversation']['expiration_timestamp'] ?? null,
            pricingCategory: $status['pricing']['category'] ?? null,
            errors: $status['errors'] ?? [],
        );
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getFirstErrorCode(): ?int
    {
        return $this->errors[0]['code'] ?? null;
    }

    public function getFirstErrorMessage(): ?string
    {
        return $this->errors[0]['message'] ?? null;
    }

    public function getFirstErrorDetails(): ?string
    {
        return $this->errors[0]['error_data']['details'] ?? null;
    }
}
