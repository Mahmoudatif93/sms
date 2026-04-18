<?php

namespace App\Domain\Conversation\Services\Telegram;

use App\Domain\Conversation\Actions\Telegram\SendTextMessageAction;
use App\Domain\Conversation\Actions\Telegram\SendFileMessageAction;
use App\Domain\Conversation\Actions\Telegram\SendReactionMessageAction;
use App\Domain\Conversation\DTOs\SendTelegramMessageDTO;
use App\Domain\Conversation\DTOs\TelegramMessageResultDTO;
use App\Domain\Conversation\Events\Telegram\TelegramMessageSent;
use App\Domain\Conversation\Events\Telegram\TelegramReactionUpdated;
use App\Domain\Conversation\Repositories\TelegramMessageRepositoryInterface;
use App\Domain\Conversation\Requests\Telegram\SendTextMessageRequest;
use App\Domain\Conversation\Requests\Telegram\SendFileMessageRequest;
use App\Domain\Conversation\Requests\Telegram\SendReactionMessageRequest;
use App\Http\Responses\ConversationMessage;
use App\Models\Channel;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TelegramMessageService
{
    public function __construct(
        private SendTextMessageAction $sendTextAction,
        private SendFileMessageAction $sendFileAction,
        private SendReactionMessageAction $sendReactionAction,
        private TelegramMessageRepositoryInterface $repository,
    ) {}

    /* ==========================================================
     |  Send Text Message
     ========================================================== */
    public function sendTextMessage(Request $request, Conversation $conversation): JsonResponse
    {
        if (!$this->ensureTelegramConfiguration($conversation)) {
            return $this->errorResponse('Telegram configuration not found', null, 400);
        }

        $formRequest = app(SendTextMessageRequest::class);
        $validator = Validator::make($request->all(), $formRequest->rules(), $formRequest->messages());

        if ($validator->fails()) {
            return $this->errorResponse('Validation Error(s)', $validator->errors()->toArray(), 422);
        }

        $dto = SendTelegramMessageDTO::fromRequest($request, $conversation);
        $result = $this->sendTextAction->execute($dto, $conversation);

        if ($result->success) {
            event(new TelegramMessageSent($result->message, $conversation));
        }

        return $this->formatResponse($result, 'Message sent successfully');
    }

    /* ==========================================================
     |  Send File Message
     ========================================================== */
    public function sendFileMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $formRequest = app(SendFileMessageRequest::class);
        $validator = Validator::make(
            $request->all(),
            $formRequest->rules(),
            $formRequest->messages()
        );

        if ($validator->fails()) {
            return $this->errorResponse(
                'Validation Error(s)',
                $validator->errors()->toArray(),
                422
            );
        }

        // Ensure Telegram configuration
        if (!$this->ensureTelegramConfiguration($conversation)) {
            return $this->errorResponse(
                'Telegram configuration not found',
                null,
                400
            );
        }

        $result = $this->sendFileAction->execute($request, $conversation);

        // Dispatch events for real-time broadcasting
        if ($result->success) {
            $messages = is_array($result->message)
                ? $result->message
                : [$result->message];

            foreach ($messages as $message) {
                event(new TelegramMessageSent($message, $conversation));
            }
        }

        return $this->formatFilesResponse($result);
    }




    /* ==========================================================
     |  Send Reaction Message
     ========================================================== */
    public function sendReactionMessage(Request $request, Conversation $conversation): JsonResponse
    {
        if (!$this->ensureTelegramConfiguration($conversation)) {
            return $this->errorResponse('Telegram configuration not found', null, 400);
        }

        $formRequest = app(SendReactionMessageRequest::class);
        $validator = Validator::make($request->all(), $formRequest->rules(), $formRequest->messages());

        if ($validator->fails()) {
            return $this->errorResponse('Validation Error(s)', $validator->errors()->toArray(), 422);
        }

        $dto = SendTelegramMessageDTO::fromRequest($request, $conversation);
        $result = $this->sendReactionAction->execute($dto, $conversation);

        if ($result->success) {
            $emoji = $dto->content['emoji'] ?? null;
            event(new TelegramReactionUpdated($result->message, $emoji));
        }

        return $this->formatResponse($result, 'Reaction sent successfully');
    }

    /* ==========================================================
     |  Helpers
     ========================================================== */
    private function ensureTelegramConfiguration(Conversation $conversation): bool
    {
        return (bool) $conversation->channel?->connector;
    }

    private function formatResponse(TelegramMessageResultDTO $result, string $successMessage): JsonResponse
    {
        if (!$result->success) {
            return response()->json([
                'success' => false,
                'message' => $result->error,
                'errors' => $result->errors,
            ], $result->statusCode);
        }

        return response()->json([
            'success' => true,
            'message' => $successMessage,
            'data' => $result->message,
        ], 200);
    }

    private function formatFilesResponse(TelegramMessageResultDTO $result): JsonResponse
    {
        if (!$result->success) {
            return response()->json([
                'success' => false,
                'message' => $result->error,
                'errors' => $result->errors,
            ], $result->statusCode);
        }

        return response()->json([
            'success' => true,
            'message' => 'File(s) sent successfully',
            'data' => $result->message,
        ], 200);
    }

    private function errorResponse(string $message, array|null $errors = null, int $statusCode = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $statusCode);
    }
}
