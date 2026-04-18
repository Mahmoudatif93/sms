<?php

namespace App\Domain\Conversation\Actions\Widget;

use App\Domain\Conversation\Repositories\LiveChatMessageRepositoryInterface;
use App\Models\LiveChatMessage;

class MarkMessagesAsReadAction
{
    public function __construct(
        private LiveChatMessageRepositoryInterface $messageRepository,
    ) {}

    public function execute(string $sessionId, ?array $messageIds = null): array
    {
        $unreadMessages = LiveChatMessage::where('conversation_id', $sessionId)
            ->where('direction', LiveChatMessage::MESSAGE_STATUS_SENT)
            ->where(function ($query) {
                $query->where('status', '!=', LiveChatMessage::MESSAGE_STATUS_READ)
                    ->orWhere('is_read', false);
            })
            ->get();

        foreach ($unreadMessages as $message) {
            $message->markAsRead();
        }

        return [
            'updated_count' => $unreadMessages->count(),
            'messages' => $unreadMessages,
        ];
    }
}
