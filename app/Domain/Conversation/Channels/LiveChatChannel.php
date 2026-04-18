<?php

namespace App\Domain\Conversation\Channels;

use App\Domain\Conversation\Requests\LiveChat\SendMessageRequest;
use App\Domain\Conversation\Services\LiveChatMessageService;
use App\Models\Channel;
use App\Models\ContactEntity;
use App\Models\Conversation;
use App\Models\LiveChatMessage;
use App\Services\Messaging\LiveChatMessageHandler;
use App\Traits\LiveChatMessageManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LiveChatChannel extends AbstractChannel
{
    use LiveChatMessageManager;

    protected array $supportedMessageTypes = ['text', 'files', 'reaction'];

    public function __construct(
        private LiveChatMessageService $messageService,
        private LiveChatMessageHandler $messageHandler
    ) {}

    public function getPlatform(): string
    {
        return Channel::LIVECHAT_PLATFORM;
    }

    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        try {
            // Validate using Form Request rules
            $formRequest = app(SendMessageRequest::class);
            $validator = Validator::make($request->all(), $formRequest->rules(), $formRequest->messages());

            if ($validator->fails()) {
                return $this->errorResponse('Validation Error(s)', $validator->errors(), 422);
            }

            $this->activateConversation($conversation);
            $this->translateOutgoingMessage($request, $conversation);

            return parent::sendMessage($request, $conversation);
        } catch (\Throwable $e) {
            return $this->errorResponse('An error occurred: ' . $e->getMessage(), null, 500);
        }
    }

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

    public function markAsRead(Conversation $conversation): JsonResponse
    {
        $unreadMessages = $conversation->messages()
            ->where('status', '!=', LiveChatMessage::MESSAGE_STATUS_READ)
            ->get();

        $count = 0;

        foreach ($unreadMessages as $message) {
            if ($message->sender_type != ContactEntity::class) {
                continue;
            }

            $message->markAsRead();
            $count++;
            $this->messageHandler->handleAgentStatusUpdate($message, LiveChatMessage::MESSAGE_STATUS_READ);
        }

        return $this->successResponse(
            $count > 0 ? "{$count} messages marked as read." : "No unread messages found.",
            ['marked_count' => $count]
        );
    }

    public function markAsDelivered(Conversation $conversation): JsonResponse
    {
        $unreadMessages = $conversation->messages()
            ->where('status', LiveChatMessage::MESSAGE_STATUS_SENT)
            ->get();

        $count = 0;

        foreach ($unreadMessages as $message) {
            if ($message->sender_type != ContactEntity::class) {
                continue;
            }

            $message->markAsDeliverd();
            $count++;
            $this->messageHandler->handleAgentStatusUpdate($message, LiveChatMessage::MESSAGE_STATUS_DELIVERED);
        }

        return $this->successResponse(
            $count > 0 ? "{$count} messages marked as delivered." : "No messages to mark as delivered.",
            ['marked_count' => $count]
        );
    }

    public function handleClose(Conversation $conversation, string $closedBy): void
    {
        $this->messageHandler->handleConversationClosed($conversation, $closedBy);
    }

    public function handleReopen(Conversation $conversation): void
    {
        $this->messageHandler->handleConversationReopened($conversation);
    }
}
