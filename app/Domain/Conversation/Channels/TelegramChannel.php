<?php

namespace App\Domain\Conversation\Channels;

use App\Domain\Conversation\Requests\Telegram\SendMessageRequest;
use App\Domain\Conversation\Services\Telegram\TelegramMessageService;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\TelegramMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TelegramChannel extends AbstractChannel
{
    protected array $supportedMessageTypes = [
        'text',
        'files',
        'reaction',
    ];

    public function __construct(
        private TelegramMessageService $messageService
    ) {}

    public function getPlatform(): string
    {
        return Channel::TELEGRAM_PLATFORM;
    }

    /* ==========================================================
     |  Generic Send (Router)
     ========================================================== */

    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        try {
            // Validate using Telegram Form Request
            $formRequest = app(SendMessageRequest::class);
            $validator = Validator::make(
                $request->all(),
                $formRequest->rules(),
                $formRequest->messages()
            );

            if ($validator->fails()) {
                return $this->errorResponse(
                    'Validation Error(s)',
                    $validator->errors(),
                    422
                );
            }

            return parent::sendMessage($request, $conversation);
        } catch (\Throwable $e) {
            return $this->errorResponse(
                'An error occurred: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /* ==========================================================
     |  Message Types
     ========================================================== */

    public function sendTextMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->messageService->sendTextMessage($request, $conversation);
    }

    public function sendFileMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->messageService->sendFileMessage($request, $conversation);
    }

    public function sendReactionMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->messageService->sendReactionMessage($request, $conversation);
    }

    /* ==========================================================
     |  Message Status
     ========================================================== */

    public function markAsRead(Conversation $conversation): JsonResponse
    {
        $messages = $conversation->messages()
            ->where('status', '!=', TelegramMessage::MESSAGE_STATUS_READ)
            ->get();

        $count = 0;

        foreach ($messages as $message) {
            if ($message->from_agent) {
                continue;
            }

            $message->update([
                'status' => TelegramMessage::MESSAGE_STATUS_READ,
            ]);

            $count++;
        }

        return $this->successResponse(
            $count > 0
                ? "{$count} messages marked as read."
                : "No unread messages found.",
            ['marked_count' => $count]
        );
    }

    public function markAsDelivered(Conversation $conversation): JsonResponse
    {
        $messages = $conversation->messages()
            ->where('status', TelegramMessage::MESSAGE_STATUS_SENT)
            ->get();

        $count = 0;

        foreach ($messages as $message) {
            if ($message->from_agent) {
                continue;
            }

            $message->update([
                'status' => TelegramMessage::MESSAGE_STATUS_DELIVERED,
            ]);

            $count++;
        }

        return $this->successResponse(
            $count > 0
                ? "{$count} messages marked as delivered."
                : "No messages to mark as delivered.",
            ['marked_count' => $count]
        );
    }

    /* ==========================================================
     |  Conversation Lifecycle
     ========================================================== */

    public function handleClose(Conversation $conversation, string $closedBy): void
    {
        // Telegram conversations usually don't close explicitly
        // Keeping method for interface compatibility
    }

    public function handleReopen(Conversation $conversation): void
    {
        // No-op for Telegram
    }
}
