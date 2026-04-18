<?php

namespace App\Domain\Conversation\DTOs\Widget;

use Illuminate\Http\UploadedFile;

class WidgetMessageDTO
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $contentType,
        public readonly ?string $message = null,
        public readonly ?UploadedFile $file = null,
        public readonly ?string $caption = null,
        public readonly ?string $repliedMessageId = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            sessionId: $data['session_id'],
            contentType: $data['content_type'],
            message: $data['message'] ?? null,
            file: $data['file'] ?? null,
            caption: $data['caption'] ?? null,
            repliedMessageId: $data['replied_message_id'] ?? null,
        );
    }

    public function isTextMessage(): bool
    {
        return $this->contentType === 'text';
    }

    public function isFileMessage(): bool
    {
        return $this->contentType === 'file';
    }
}
