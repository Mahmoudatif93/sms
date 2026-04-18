<?php

namespace App\Domain\Conversation\Actions\Widget;

use App\Domain\Conversation\Repositories\LiveChatMessageRepositoryInterface;
use App\Models\LiveChatMessage;

class MarkMessagesAsDeliveredAction
{
    public function __construct(
        private LiveChatMessageRepositoryInterface $messageRepository,
    ) {}

    public function execute(array $messageIds): array
    {
        $updatedMessages = [];

        foreach ($messageIds as $messageId) {
            $message = LiveChatMessage::find($messageId);

            if ($message && $message->status === LiveChatMessage::MESSAGE_STATUS_SENT) {
                $message->update([
                    'status' => LiveChatMessage::MESSAGE_STATUS_DELIVERED,
                ]);

                $this->messageRepository->saveMessageStatus($message->id, LiveChatMessage::MESSAGE_STATUS_DELIVERED);
                $updatedMessages[] = $message;
            }
        }

        return [
            'updated_count' => count($updatedMessages),
            'messages' => $updatedMessages,
        ];
    }
}
