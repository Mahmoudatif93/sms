<?php

namespace App\Domain\Conversation\DTOs;

use App\Models\Conversation;
use Illuminate\Http\Request;

readonly class SendTelegramMessageDTO
{
    public function __construct(
        public string $conversationId,
        public string $channelId,
        public string $workspaceId,
        public string $chatId, // Telegram chat_id
        public string $type,
        public array|string|null $content,
        public ?string $agentId = null,
        public ?string $replyToMessageId = null,
    ) {}

    public static function fromRequest(Request $request, Conversation $conversation): self
    {
        $type = $request->input('type');

        $content = match ($type) {
            'text'      => $request->input('message'),
            'file'      => $request->file('file'),
            'image'     => $request->file('image'),
            'video'     => $request->file('video'),
            'audio'     => $request->file('audio'),
            'document'  => $request->file('document'),
            'location'  => [
                'latitude'  => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
            ],
            'reaction'  => $request->input('reaction'),
            default     => null,
        };

        return new self(
            conversationId: $conversation->id,
            channelId: $conversation->channel_id,
            workspaceId: $conversation->workspace_id,
            chatId: $conversation->external_chat_id, // Telegram chat_id
            type: $type,
            content: $content,
            agentId: auth('api')->id(),
            replyToMessageId: $request->input('reply_to_message_id')
                ?? $request->input('context.message_id'),
        );
    }
}
