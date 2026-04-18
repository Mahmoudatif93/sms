<?php

namespace App\Domain\Messenger\DTOs;

use App\Models\MessengerMessage;

readonly class MessengerMessageResultDTO
{
    public function __construct(
        public bool $success,
        public MessengerMessage|array|null $message = null,
        public ?string $error = null,
        public int $statusCode = 200,
        public array $errors = [],
    ) {}

    public static function success(MessengerMessage|array $message, array $errors = []): self
    {
        return new self(
            success: true,
            message: $message,
            statusCode: 200,
            errors: $errors,
        );
    }

    public static function failure(string $error, int $statusCode = 400, array $errors = []): self
    {
        return new self(
            success: false,
            error: $error,
            statusCode: $statusCode,
            errors: $errors,
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
