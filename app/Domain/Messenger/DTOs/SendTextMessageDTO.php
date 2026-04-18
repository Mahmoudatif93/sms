<?php

namespace App\Domain\Messenger\DTOs;

class SendTextMessageDTO
{
    public function __construct(
        public readonly string $pageId,
        public readonly string $recipientPsid,
        public readonly string $text,
        public readonly ?string $conversationId = null,
        public readonly string $messagingType = 'RESPONSE',
        public readonly ?string $replyToMessageId = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            pageId: $data['from'],
            recipientPsid: $data['to'],
            text: $data['text'],
            conversationId: $data['conversation_id'] ?? null,
            messagingType: $data['messaging_type'] ?? 'RESPONSE',
            replyToMessageId: $data['context']['message_id'] ?? null,
        );
    }

    public function withMessagingType(string $messagingType): self
    {
        return new self(
            pageId: $this->pageId,
            recipientPsid: $this->recipientPsid,
            text: $this->text,
            conversationId: $this->conversationId,
            messagingType: $messagingType,
            replyToMessageId: $this->replyToMessageId,
        );
    }

    public function hasReplyTo(): bool
    {
        return $this->replyToMessageId !== null;
    }
}
