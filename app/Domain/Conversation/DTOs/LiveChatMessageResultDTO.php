<?php

namespace App\Domain\Conversation\DTOs;

use App\Models\LiveChatMessage;

readonly class LiveChatMessageResultDTO
{
    public function __construct(
        public bool $success,
        public LiveChatMessage|array|null $message = null,
        public ?string $error = null,
        public int $statusCode = 200,
        public array $errors = [],
    ) {}

    public static function success(LiveChatMessage|array $message, array $errors = []): self
    {
        return new self(
            success: true,
            message: $message,
            statusCode: 200,
            errors: $errors,
        );
    }

    public static function failure(string $error, int $statusCode = 400): self
    {
        return new self(
            success: false,
            error: $error,
            statusCode: $statusCode,
        );
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'error' => $this->error,
            'errors' => $this->errors,
        ];
    }
}
