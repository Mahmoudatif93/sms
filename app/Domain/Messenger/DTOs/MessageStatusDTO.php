<?php

namespace App\Domain\Messenger\DTOs;

class MessageStatusDTO
{
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';

    public function __construct(
        public readonly string $pageId,
        public readonly string $senderId,
        public readonly string $recipientId,
        public readonly string $status,
        public readonly array $messageIds,
        public readonly int $watermark,
        public readonly string $timestamp,
    ) {}

    public static function fromDeliveryEvent(string $pageId, array $event): self
    {
        return new self(
            pageId: $pageId,
            senderId: $event['sender']['id'] ?? '',
            recipientId: $event['recipient']['id'] ?? '',
            status: self::STATUS_DELIVERED,
            messageIds: $event['delivery']['mids'] ?? [],
            watermark: (int) ($event['delivery']['watermark'] ?? 0),
            timestamp: (string) ($event['timestamp'] ?? ''),
        );
    }

    public static function fromReadEvent(string $pageId, array $event): self
    {
        return new self(
            pageId: $pageId,
            senderId: $event['sender']['id'] ?? '',
            recipientId: $event['recipient']['id'] ?? '',
            status: self::STATUS_READ,
            messageIds: [],
            watermark: (int) ($event['read']['watermark'] ?? 0),
            timestamp: (string) ($event['timestamp'] ?? ''),
        );
    }

    public function isValid(): bool
    {
        return !empty($this->senderId) && !empty($this->recipientId);
    }

    public function hasMessageIds(): bool
    {
        return !empty($this->messageIds);
    }

    public function isDelivery(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    public function isRead(): bool
    {
        return $this->status === self::STATUS_READ;
    }
}
