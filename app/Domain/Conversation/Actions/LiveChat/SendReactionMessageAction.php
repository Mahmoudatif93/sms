<?php

namespace App\Domain\Conversation\Actions\LiveChat;

use App\Domain\Conversation\DTOs\LiveChatMessageResultDTO;
use App\Domain\Conversation\DTOs\SendLiveChatMessageDTO;
use App\Domain\Conversation\Repositories\LiveChatMessageRepositoryInterface;
use App\Models\LiveChatMessage;
use Illuminate\Support\Facades\Log;

class SendReactionMessageAction
{
    public function __construct(
        private LiveChatMessageRepositoryInterface $repository,
    ) {}

    public function execute(SendLiveChatMessageDTO $dto): LiveChatMessageResultDTO
    {
        try {
            $messageId = $dto->content['message_id'];
            $emoji = $dto->content['emoji'] ?? '';

            // Find the message to react to via Repository
            $livechatMessage = $this->repository->findByIdInConversation($messageId, $dto->conversationId);

            if (!$livechatMessage) {
                return LiveChatMessageResultDTO::failure('Message not found', 404);
            }

            $isRemoval = empty($emoji);

            if ($isRemoval) {
                // Delete reaction via Repository
                $this->repository->deleteReaction($messageId, LiveChatMessage::MESSAGE_DIRECTION_SENT);
            } else {
                // Create or update reaction via Repository
                $this->repository->upsertReaction(
                    $messageId,
                    $emoji,
                    LiveChatMessage::MESSAGE_DIRECTION_SENT
                );
            }

            return LiveChatMessageResultDTO::success($livechatMessage);

        } catch (\Exception $e) {
            Log::error('LiveChat SendReactionMessageAction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return LiveChatMessageResultDTO::failure('An error occurred: ' . $e->getMessage(), 500);
        }
    }
}
