<?php

namespace App\Domain\Messenger\DTOs;

class SendAttachmentDTO
{
    public const TYPE_IMAGE = 'image';
    public const TYPE_VIDEO = 'video';
    public const TYPE_AUDIO = 'audio';
    public const TYPE_FILE = 'file';

    public const VALID_TYPES = [
        self::TYPE_IMAGE,
        self::TYPE_VIDEO,
        self::TYPE_AUDIO,
        self::TYPE_FILE,
    ];

    public function __construct(
        public readonly string $pageId,
        public readonly string $recipientPsid,
        public readonly string $type,
        public readonly string $url,
        public readonly ?string $conversationId = null,
        public readonly ?string $filename = null,
        public readonly bool $isReusable = false,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            pageId: $data['page_id'],
            recipientPsid: $data['recipient_psid'],
            type: $data['type'],
            url: $data['url'],
            conversationId: $data['conversation_id'] ?? null,
            filename: $data['filename'] ?? null,
            isReusable: $data['is_reusable'] ?? false,
        );
    }

    public function isValidType(): bool
    {
        return in_array($this->type, self::VALID_TYPES);
    }
}
