<?php

namespace App\Domain\Messenger\DTOs;

class MessengerSenderDTO
{
    public function __construct(
        public readonly string $psid,
        public readonly string $pageId,
        public readonly ?string $name = null,
    ) {}

    public static function fromWebhookData(string $psid, string $pageId, ?string $name = null): self
    {
        return new self(
            psid: $psid,
            pageId: $pageId,
            name: $name,
        );
    }
}
