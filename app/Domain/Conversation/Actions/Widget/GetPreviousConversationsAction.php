<?php

namespace App\Domain\Conversation\Actions\Widget;

use App\Domain\Conversation\Repositories\WidgetRepositoryInterface;
use App\Models\LiveChatMessage;
use App\Models\LiveChatTextMessage;
use App\Models\LiveChatFileMessage;
use App\Models\PreChatFormFieldResponse;

class GetPreviousConversationsAction
{
    public function __construct(
        private WidgetRepositoryInterface $widgetRepository,
    ) {}

    public function execute(string $contactId, string $widgetId): array
    {
        $conversations = $this->widgetRepository->getPreviousConversations($contactId, $widgetId, 5);

        $formattedConversations = $conversations->map(function ($conversation) {
            $firstMessage = LiveChatMessage::where('conversation_id', $conversation->id)
                ->orderBy('created_at', 'asc')
                ->first();

            $firstMessagePreview = $this->getMessagePreview($firstMessage);

            return [
                'id' => $conversation->id,
                'started_at' => $conversation->started_at,
                'ended_at' => $conversation->ended_at,
                'duration' => $conversation->started_at && $conversation->ended_at
                    ? $conversation->ended_at->diffInMinutes($conversation->started_at)
                    : null,
                'message_preview' => $firstMessagePreview,
                'agent_name' => optional($conversation->agent)->name,
            ];
        });

        return [
            'sessions' => $formattedConversations,
        ];
    }

    private function getMessagePreview(?LiveChatMessage $message): ?string
    {
        if (!$message) {
            return null;
        }

        if ($message->messageable_type === LiveChatTextMessage::class) {
            return substr($message->messageable->text, 0, 100);
        }

        if ($message->messageable_type === PreChatFormFieldResponse::class) {
            return 'Pre-chat information submitted';
        }

        if ($message->messageable_type === LiveChatFileMessage::class) {
            return 'File: ' . $message->messageable->file_name;
        }

        return null;
    }
}
