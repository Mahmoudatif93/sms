<?php

namespace App\Domain\Conversation\Actions\Telegram;

use App\Domain\Conversation\DTOs\TelegramMessageResultDTO;
use App\Domain\Conversation\Repositories\TelegramMessageRepositoryInterface;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SendFileMessageAction
{
    public function __construct(
        private TelegramMessageRepositoryInterface $repository,
    ) {}

    public function execute(
        Request $request,
        Conversation $conversation
    ): TelegramMessageResultDTO {
        try {
            $files = $request->file('files', []);

            if (empty($files)) {
                return TelegramMessageResultDTO::failure('No files provided', 422);
            }

            $messages = [];

            foreach ($files as $file) {
                $messages[] = $this->repository->createFileMessage(
                    conversationId: $conversation->id,
                    chatId: $conversation->external_chat_id,
                    type: $this->detectFileType($file->getMimeType()),
                    caption: $request->input('caption'),
                    filePath: $file->store('telegram/files'),
                    fromAgent: true,
                    replyToMessageId: $request->input('reply_to_message_id')
                );
            }

            return TelegramMessageResultDTO::success($messages);
        } catch (\Throwable $e) {
            Log::error('Telegram SendFileMessageAction failed', [
                'error' => $e->getMessage(),
            ]);

            return TelegramMessageResultDTO::failure(
                'Failed to send telegram file message',
                500
            );
        }
    }

    private function detectFileType(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            default => 'document',
        };
    }
}
