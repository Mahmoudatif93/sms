<?php

namespace App\Domain\Conversation\Actions\LiveChat;

use App\Domain\Conversation\DTOs\LiveChatMessageResultDTO;
use App\Domain\Conversation\DTOs\SendLiveChatMessageDTO;
use App\Domain\Conversation\Repositories\LiveChatMessageRepositoryInterface;
use App\Models\Conversation;
use App\Models\LiveChatMessage;
use App\Models\LiveChatTextMessage;
use App\Models\Widget;
use Illuminate\Support\Facades\Log;

class SendTextMessageAction
{
    public function __construct(
        private LiveChatMessageRepositoryInterface $repository,
    ) {}

    public function execute(SendLiveChatMessageDTO $dto, Conversation $conversation): LiveChatMessageResultDTO
    {
        try {
            // Create text message content via Repository
            $textMessage = $this->repository->createTextMessage($dto->content);

            // Create main message via Repository
            $message = $this->repository->createForConversation($conversation->id, [
                'channel_id' => $dto->channelId,
                'workspace_id' => $dto->workspaceId,
                'widget_id' => $dto->widgetId,
                'type' => 'text',
                'status' => LiveChatMessage::MESSAGE_STATUS_SENT,
                'agent_id' => $dto->agentId,
                'direction' => LiveChatMessage::MESSAGE_STATUS_SENT,
                'content' => $dto->content,
                'sender_type' => Widget::class,
                'sender_id' => $dto->widgetId,
                'messageable_type' => LiveChatTextMessage::class,
                'messageable_id' => $textMessage->id,
                'is_read' => false,
                'replied_to_message_id' => $dto->replyToMessageId,
            ]);

            // Save message status via Repository
            $this->repository->saveMessageStatus($message->id, 'sent');

            return LiveChatMessageResultDTO::success($message);

        } catch (\Exception $e) {
            Log::error('LiveChat SendTextMessageAction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return LiveChatMessageResultDTO::failure('An error occurred: ' . $e->getMessage(), 500);
        }
    }
}
