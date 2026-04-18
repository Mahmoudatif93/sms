<?php

namespace App\Domain\Conversation\Actions\Telegram;

use App\Domain\Conversation\DTOs\SendTelegramMessageDTO;
use App\Domain\Conversation\DTOs\TelegramMessageResultDTO;
use App\Domain\Conversation\Repositories\TelegramMessageRepositoryInterface;
use App\Models\TelegramMessage;
use Illuminate\Support\Facades\Log;

class SendReactionMessageAction
{
    public function __construct(
        private TelegramMessageRepositoryInterface $repository,
    ) {}

    public function execute(SendTelegramMessageDTO $dto, $conversation = null): TelegramMessageResultDTO
    {
        try {
            $messageId = $dto->content['message_id'] ?? null;
            $emoji = $dto->content['emoji'] ?? '';

            if (!$messageId) {
                return TelegramMessageResultDTO::failure('Message ID is required', 422);
            }

            // Find the message to react to via Repository
            $telegramMessage = $this->repository->findByIdInConversation($messageId, $dto->conversationId);

            if (!$telegramMessage) {
                return TelegramMessageResultDTO::failure('Message not found', 404);
            }

            $isRemoval = empty($emoji);

            if ($isRemoval) {
                // Delete reaction via Repository
                $this->repository->deleteReaction($messageId, TelegramMessage::MESSAGE_DIRECTION_SENT);
            } else {
                // Create or update reaction via Repository
                $this->repository->upsertReaction(
                    $messageId,
                    $emoji,
                    TelegramMessage::MESSAGE_DIRECTION_SENT
                );
            }

            return TelegramMessageResultDTO::success($telegramMessage);
        } catch (\Exception $e) {
            Log::error('Telegram SendReactionMessageAction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return TelegramMessageResultDTO::failure('An error occurred: ' . $e->getMessage(), 500);
        }
    }
}
