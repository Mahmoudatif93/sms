<?php

namespace App\Domain\WhatsApp\DTOs;

class WebhookPayloadDTO
{
    public function __construct(
        public readonly string $whatsappBusinessAccountId,
        public readonly string $phoneNumberId,
        public readonly array $messages,
        public readonly array $statuses,
        public readonly array $contactsMap,
        public readonly array $rawPayload,
    ) {}

    public static function fromWebhookEntry(string $whatsappBusinessAccountId, array $value): self
    {
        $phoneNumberId = $value['metadata']['phone_number_id'] ?? '';

        // Build contacts map
        $contactsMap = [];
        foreach ($value['contacts'] ?? [] as $contact) {
            if (isset($contact['wa_id'], $contact['profile']['name'])) {
                $contactsMap[$contact['wa_id']] = $contact['profile']['name'];
            }
        }

        return new self(
            whatsappBusinessAccountId: $whatsappBusinessAccountId,
            phoneNumberId: $phoneNumberId,
            messages: $value['messages'] ?? [],
            statuses: $value['statuses'] ?? [],
            contactsMap: $contactsMap,
            rawPayload: $value,
        );
    }

    public function hasMessages(): bool
    {
        return !empty($this->messages);
    }

    public function hasStatuses(): bool
    {
        return !empty($this->statuses);
    }

    public function getIncomingMessageDTOs(): array
    {
        $dtos = [];
        foreach ($this->messages as $message) {
            $dtos[] = IncomingMessageDTO::fromWebhookData(
                $message,
                $this->whatsappBusinessAccountId,
                $this->phoneNumberId,
                $this->contactsMap
            );
        }
        return $dtos;
    }

    public function getStatusDTOs(): array
    {
        $dtos = [];
        foreach ($this->statuses as $status) {
            $dtos[] = MessageStatusDTO::fromWebhookData($status, $this->phoneNumberId);
        }
        return $dtos;
    }
}
