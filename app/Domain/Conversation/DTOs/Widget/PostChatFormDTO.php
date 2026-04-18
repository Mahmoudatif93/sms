<?php

namespace App\Domain\Conversation\DTOs\Widget;

class PostChatFormDTO
{
    public function __construct(
        public readonly string $sessionId,
        public readonly array $formData,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            sessionId: $data['session_id'],
            formData: $data['form_data'],
        );
    }
}
