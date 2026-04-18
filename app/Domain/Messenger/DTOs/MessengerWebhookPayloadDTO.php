<?php

namespace App\Domain\Messenger\DTOs;

class MessengerWebhookPayloadDTO
{
    public function __construct(
        public readonly string $pageId,
        public readonly array $messagingEvents,
        public readonly array $rawPayload,
    ) {}

    public static function fromWebhookEntry(array $entry): self
    {
        return new self(
            pageId: $entry['id'] ?? '',
            messagingEvents: $entry['messaging'] ?? [],
            rawPayload: $entry,
        );
    }

    public function hasMessagingEvents(): bool
    {
        return !empty($this->messagingEvents);
    }

    public function getMessageDTOs(): array
    {
        $dtos = [];
        foreach ($this->messagingEvents as $event) {
            if (isset($event['message'])) {
                $dtos[] = MessengerMessageDTO::fromWebhookData($this->pageId, $event);
            }
        }
        return $dtos;
    }

    public function getStatusDTOs(): array
    {
        $dtos = [];
        foreach ($this->messagingEvents as $event) {
            if (isset($event['delivery'])) {
                $dtos[] = MessageStatusDTO::fromDeliveryEvent($this->pageId, $event);
            } elseif (isset($event['read'])) {
                $dtos[] = MessageStatusDTO::fromReadEvent($this->pageId, $event);
            }
        }
        return $dtos;
    }

    public function hasStatusEvents(): bool
    {
        foreach ($this->messagingEvents as $event) {
            if (isset($event['delivery']) || isset($event['read'])) {
                return true;
            }
        }
        return false;
    }
}
