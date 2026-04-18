<?php

namespace App\Domain\WhatsApp\DTOs;

class MessageContextDTO
{
    public function __construct(
        public readonly ?string $repliedToMessageId = null,
        public readonly ?string $repliedToMessageFrom = null,
    ) {}

    public static function fromWebhookData(?array $context): self
    {
        if (!$context) {
            return new self();
        }

        return new self(
            repliedToMessageId: $context['id'] ?? null,
            repliedToMessageFrom: $context['from'] ?? null,
        );
    }
}
