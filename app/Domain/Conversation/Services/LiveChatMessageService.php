<?php

namespace App\Domain\Conversation\Services;

use App\Domain\Conversation\Actions\LiveChat\SendFileMessageAction;
use App\Domain\Conversation\Actions\LiveChat\SendReactionMessageAction;
use App\Domain\Conversation\Actions\LiveChat\SendTextMessageAction;
use App\Domain\Conversation\DTOs\LiveChatMessageResultDTO;
use App\Domain\Conversation\DTOs\SendLiveChatMessageDTO;
use App\Domain\Conversation\Events\LiveChat\LiveChatMessageSent;
use App\Domain\Conversation\Events\LiveChat\LiveChatReactionUpdated;
use App\Domain\Conversation\Repositories\LiveChatMessageRepositoryInterface;
use App\Domain\Conversation\Requests\LiveChat\SendFileMessageRequest;
use App\Domain\Conversation\Requests\LiveChat\SendReactionMessageRequest;
use App\Domain\Conversation\Requests\LiveChat\SendTextMessageRequest;
use App\Http\Responses\ConversationMessage;
use App\Models\Channel;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * LiveChatMessageService - Orchestration Layer
 *
 * This service orchestrates message sending by:
 * 1. Validating input (using Form Requests)
 * 2. Creating DTOs
 * 3. Delegating to appropriate Actions
 * 4. Formatting responses
 */
class LiveChatMessageService
{
    public function __construct(
        private SendTextMessageAction $sendTextAction,
        private SendFileMessageAction $sendFileAction,
        private SendReactionMessageAction $sendReactionAction,
        private LiveChatMessageRepositoryInterface $repository,
    ) {}

    public function sendTextMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $formRequest = app(SendTextMessageRequest::class);
        $validator = Validator::make($request->all(), $formRequest->rules(), $formRequest->messages());

        if ($validator->fails()) {
            return $this->errorResponse('Validation Error(s)', $validator->errors()->toArray(), 422);
        }

        $widgetId = $this->getWidgetId($conversation);
        if (!$widgetId) {
            return $this->errorResponse('LiveChat configuration not found', null, 400);
        }

        $dto = SendLiveChatMessageDTO::fromRequest($request, $conversation, $widgetId);
        $result = $this->sendTextAction->execute($dto, $conversation);

        // Dispatch event for real-time broadcasting
        if ($result->success) {
            event(new LiveChatMessageSent($result->message, $conversation));
        }

        return $this->formatResponse($result, 'Message sent successfully');
    }

    public function sendFileMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $formRequest = app(SendFileMessageRequest::class);
        $validator = Validator::make($request->all(), $formRequest->rules(), $formRequest->messages());

        if ($validator->fails()) {
            return $this->errorResponse('Validation Error(s)', $validator->errors()->toArray(), 422);
        }

        $widgetId = $this->getWidgetId($conversation);
        if (!$widgetId) {
            return $this->errorResponse('LiveChat configuration not found', null, 400);
        }

        $result = $this->sendFileAction->execute($request, $conversation, $widgetId);

        // Dispatch events for real-time broadcasting
        if ($result->success) {
            $messages = is_array($result->message) ? $result->message : [$result->message];
            foreach ($messages as $message) {
                event(new LiveChatMessageSent($message, $conversation));
            }
        }

        return $this->formatFilesResponse($result);
    }

    public function sendReactionMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $formRequest = app(SendReactionMessageRequest::class);
        $validator = Validator::make($request->all(), $formRequest->rules(), $formRequest->messages());

        if ($validator->fails()) {
            return $this->errorResponse('Validation Error(s)', $validator->errors()->toArray(), 422);
        }

        $widgetId = $this->getWidgetId($conversation);
        if (!$widgetId) {
            return $this->errorResponse('LiveChat configuration not found', null, 400);
        }

        $dto = SendLiveChatMessageDTO::fromRequest($request, $conversation, $widgetId);
        $result = $this->sendReactionAction->execute($dto);

        // Dispatch event for real-time broadcasting
        if ($result->success) {
            $emoji = $dto->content['emoji'] ?? null;
            event(new LiveChatReactionUpdated($result->message, $emoji));
        }

        return $this->formatResponse($result, 'Reaction sent successfully');
    }

    // ========================================
    // Helper Methods
    // ========================================

    private function getWidgetId(Conversation $conversation): ?string
    {
        $connector = $conversation->channel->connector;
        $config = $this->repository->getLiveChatConfiguration($connector->id);

        return $config?->widget_id;
    }

    // ========================================
    // Response Formatting
    // ========================================

    private function formatResponse(LiveChatMessageResultDTO $result, string $successMessage): JsonResponse
    {
        if (!$result->success) {
            return $this->errorResponse($result->error, null, $result->statusCode);
        }

        return $this->successResponse(
            $successMessage,
            new ConversationMessage($result->message, Channel::LIVECHAT_PLATFORM)
        );
    }

    private function formatFilesResponse(LiveChatMessageResultDTO $result): JsonResponse
    {
        if (!$result->success) {
            return $this->errorResponse($result->error, null, $result->statusCode);
        }

        $messages = is_array($result->message)
            ? array_map(fn($msg) => new ConversationMessage($msg, Channel::LIVECHAT_PLATFORM), $result->message)
            : [new ConversationMessage($result->message, Channel::LIVECHAT_PLATFORM)];

        return response()->json([
            'success' => true,
            'message' => 'Messages sent successfully',
            'data' => [
                'messages' => $messages,
                'errors' => $result->errors,
            ],
        ]);
    }

    private function successResponse(string $message, $data = null): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ]);
    }

    private function errorResponse(string $message, $errors = null, int $statusCode = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $errors,
        ], $statusCode);
    }
}
