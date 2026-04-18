<?php

namespace App\Domain\Conversation\DTOs\Widget;

class WidgetReactionDTO
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $messageId,
        public readonly ?string $emoji = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            sessionId: $data['session_id'],
            messageId: $data['message_id'],
            emoji: $data['emoji'] ?? null,
        );
    }

    public function isRemoval(): bool
    {
        return empty($this->emoji);
    }
}
