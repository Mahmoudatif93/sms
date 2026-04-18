<?php

namespace App\Domain\Conversation\Actions\Telegram;

use App\Domain\Conversation\DTOs\TelegramMessageResultDTO;
use App\Domain\Conversation\DTOs\SendTelegramMessageDTO;
use App\Domain\Conversation\Repositories\TelegramMessageRepositoryInterface;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;

class SendTextMessageAction
{
    public function __construct(
        private TelegramMessageRepositoryInterface $repository,
    ) {}

    public function execute(
        SendTelegramMessageDTO $dto,
        Conversation $conversation
    ): TelegramMessageResultDTO {
        try {
            $message = $this->repository->createTextMessage(
                conversationId: $conversation->id,
                chatId: $dto->chatId,
                text: $dto->content,
                fromAgent: true,
                replyToMessageId: $dto->replyToMessageId
            );

            return TelegramMessageResultDTO::success($message);
        } catch (\Throwable $e) {
            Log::error('Telegram SendTextMessageAction failed', [
                'error' => $e->getMessage(),
            ]);

            return TelegramMessageResultDTO::failure(
                'Failed to send telegram text message',
                500
            );
        }
    }
}
