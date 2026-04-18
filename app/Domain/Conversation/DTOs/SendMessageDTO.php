<?php

namespace App\Domain\Conversation\DTOs;

use Illuminate\Http\Request;

final readonly class SendMessageDTO
{
    public function __construct(
        public string $conversationId,
        public string $type,
        public ?string $text = null,
        public ?array $media = null,
        public ?string $templateId = null,
        public ?array $context = null,
        public ?string $replyToMessageId = null,
        public ?array $files = null,
        public ?array $location = null,
        public ?array $interactive = null,
        public ?array $flow = null,
        public ?array $reaction = null,
    ) {}

    public static function fromRequest(Request $request, string $conversationId): self
    {
        return new self(
            conversationId: $conversationId,
            type: $request->input('type'),
            text: $request->input('text.body') ?? $request->input('message'),
            media: $request->input('media'),
            templateId: $request->input('template_id'),
            context: $request->input('context'),
            replyToMessageId: $request->input('reply_to_message_id') ?? $request->input('context.message_id'),
            files: $request->file('files'),
            location: $request->input('location'),
            interactive: $request->input('interactive'),
            flow: $request->input('flow'),
            reaction: $request->input('content'),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'conversation_id' => $this->conversationId,
            'type' => $this->type,
            'text' => $this->text,
            'media' => $this->media,
            'template_id' => $this->templateId,
            'context' => $this->context,
            'reply_to_message_id' => $this->replyToMessageId,
            'files' => $this->files,
            'location' => $this->location,
            'interactive' => $this->interactive,
            'flow' => $this->flow,
            'reaction' => $this->reaction,
        ], fn($value) => $value !== null);
    }
}
