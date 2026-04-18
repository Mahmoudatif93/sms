<?php

namespace App\Domain\Conversation\DTOs;

final readonly class MessageResultDTO
{
    public function __construct(
        public bool $success,
        public string $message,
        public mixed $data = null,
        public int $statusCode = 200,
        public ?string $messageId = null,
        public ?string $status = null,
        public ?array $errors = null,
    ) {}

    public static function success(string $message, mixed $data = null, ?string $messageId = null): self
    {
        return new self(
            success: true,
            message: $message,
            data: $data,
            statusCode: 200,
            messageId: $messageId,
            status: 'sent',
        );
    }

    public static function failure(string $message, ?array $errors = null, int $statusCode = 400): self
    {
        return new self(
            success: false,
            message: $message,
            statusCode: $statusCode,
            errors: $errors,
            status: 'failed',
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
            'message_id' => $this->messageId,
            'status' => $this->status,
            'errors' => $this->errors,
        ], fn($value) => $value !== null);
    }

    public function toResponse(): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->toArray(), $this->statusCode);
    }
}
