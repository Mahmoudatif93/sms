<?php

namespace App\Domain\Conversation\DTOs\Widget;

class PreChatFormDTO
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

    public function getName(): ?string
    {
        return $this->formData['name'] ?? null;
    }

    public function getEmail(): ?string
    {
        return $this->formData['email'] ?? null;
    }

    public function getPhone(): ?string
    {
        return $this->formData['phone'] ?? null;
    }
}
