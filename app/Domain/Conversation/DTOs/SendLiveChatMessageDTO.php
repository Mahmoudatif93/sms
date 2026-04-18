<?php

namespace App\Domain\Conversation\DTOs;

use App\Models\Conversation;
use Illuminate\Http\Request;

readonly class SendLiveChatMessageDTO
{
    public function __construct(
        public string $conversationId,
        public string $channelId,
        public string $workspaceId,
        public string $widgetId,
        public string $type,
        public array|string|null $content,
        public ?string $agentId = null,
        public ?string $replyToMessageId = null,
    ) {}

    public static function fromRequest(Request $request, Conversation $conversation, string $widgetId): self
    {
        $type = $request->input('type');

        $content = match ($type) {
            'text' => $request->input('message'),
            'files' => $request->file('files'),
            'reaction' => $request->input('reaction'),
            default => null,
        };

        return new self(
            conversationId: $conversation->id,
            channelId: $conversation->channel_id,
            workspaceId: $conversation->workspace_id,
            widgetId: $widgetId,
            type: $type,
            content: $content,
            agentId: auth('api')->id(),
            replyToMessageId: $request->input('reply_to_message_id') ?? $request->input('context.message_id'),
        );
    }
}
