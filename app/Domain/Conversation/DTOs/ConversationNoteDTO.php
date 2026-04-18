<?php

namespace App\Domain\Conversation\DTOs;

use Illuminate\Http\Request;

final readonly class ConversationNoteDTO
{
    public function __construct(
        public string $content,
        public string $userId,
        public ?string $conversationId = null,
        public bool $isSystemNote = false,
    ) {}

    public static function fromRequest(Request $request, string $userId, ?string $conversationId = null): self
    {
        return new self(
            content: $request->input('content'),
            userId: $userId,
            conversationId: $conversationId,
            isSystemNote: false,
        );
    }

    public static function systemNote(string $content, string $userId, ?string $conversationId = null): self
    {
        return new self(
            content: $content,
            userId: $userId,
            conversationId: $conversationId,
            isSystemNote: true,
        );
    }

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'user_id' => $this->userId,
            'is_system_note' => $this->isSystemNote,
        ];
    }
}
