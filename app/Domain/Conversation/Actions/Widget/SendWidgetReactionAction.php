<?php

namespace App\Domain\Conversation\Actions\Widget;

use App\Domain\Conversation\DTOs\Widget\WidgetReactionDTO;
use App\Models\Conversation;
use App\Models\LiveChatMessage;
use App\Models\LiveChatReactionMessage;

class SendWidgetReactionAction
{
    public function execute(WidgetReactionDTO $dto): array
    {
        $conversation = Conversation::findOrFail($dto->sessionId);

        $message = LiveChatMessage::where('id', $dto->messageId)
            ->where('conversation_id', $dto->sessionId)
            ->firstOrFail();

        if ($dto->isRemoval()) {
            LiveChatReactionMessage::where('livechat_message_id', $message->id)
                ->where('direction', LiveChatMessage::MESSAGE_DIRECTION_RECEIVED)
                ->delete();
        } else {
            LiveChatReactionMessage::updateOrCreate(
                [
                    'livechat_message_id' => $message->id,
                    'direction' => LiveChatMessage::MESSAGE_DIRECTION_RECEIVED,
                ],
                ['emoji' => $dto->emoji]
            );

            $conversation->last_message_at = now();
            $conversation->save();
        }

        return [
            'message_id' => $message->id,
            'emoji' => $dto->emoji,
        ];
    }
}
