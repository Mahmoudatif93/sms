<?php

namespace App\Domain\Messenger\DTOs;

class MessengerMessageDTO
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $pageId,
        public readonly string $senderId,
        public readonly string $recipientId,
        public readonly string $timestamp,
        public readonly string $type,
        public readonly array $payload,
    ) {}

    public static function fromWebhookData(string $pageId, array $event): self
    {
        $message = $event['message'] ?? [];
        $type = $message['attachments'][0]['type'] ?? 'text';

        return new self(
            messageId: $message['mid'] ?? '',
            pageId: $pageId,
            senderId: $event['sender']['id'] ?? '',
            recipientId: $event['recipient']['id'] ?? '',
            timestamp: (string) ($event['timestamp'] ?? ''),
            type: $type,
            payload: $message,
        );
    }

    public function getText(): ?string
    {
        return $this->payload['text'] ?? null;
    }

    public function getAttachments(): array
    {
        return $this->payload['attachments'] ?? [];
    }

    public function isValid(): bool
    {
        return !empty($this->senderId) && !empty($this->recipientId) && !empty($this->payload);
    }
}
