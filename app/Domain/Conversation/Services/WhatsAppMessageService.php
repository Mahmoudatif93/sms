<?php

namespace App\Domain\Conversation\Services;

use App\Domain\Conversation\Actions\WhatsApp\SendFilesMessageAction;
use App\Domain\Conversation\Actions\WhatsApp\SendFlowMessageAction;
use App\Domain\Conversation\Actions\WhatsApp\SendInteractiveMessageAction;
use App\Domain\Conversation\Actions\WhatsApp\SendLocationMessageAction;
use App\Domain\Conversation\Actions\WhatsApp\SendMediaMessageAction;
use App\Domain\Conversation\Actions\WhatsApp\SendReactionMessageAction;
use App\Domain\Conversation\Actions\WhatsApp\SendStickerMessageAction;
use App\Domain\Conversation\Actions\WhatsApp\SendTemplateMessageAction;
use App\Domain\Conversation\Actions\WhatsApp\SendTextMessageAction;
use App\Domain\Conversation\DTOs\SendWhatsAppMessageDTO;
use App\Domain\Conversation\DTOs\WhatsAppMessageResultDTO;
use App\Http\Responses\ConversationMessage;
use App\Models\Channel;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * WhatsAppMessageService - Orchestration Layer
 *
 * This service orchestrates message sending by:
 * 1. Validating input
 * 2. Creating DTOs
 * 3. Delegating to appropriate Actions
 * 4. Formatting responses
 */
class WhatsAppMessageService
{
    public function __construct(
        private SendTextMessageAction $sendTextAction,
        private SendMediaMessageAction $sendMediaAction,
        private SendLocationMessageAction $sendLocationAction,
        private SendStickerMessageAction $sendStickerMessageAction,
        private SendReactionMessageAction $sendReactionAction,
        private SendTemplateMessageAction $sendTemplateAction,
        private SendInteractiveMessageAction $sendInteractiveAction,
        private SendFlowMessageAction $sendFlowAction,
        private SendFilesMessageAction $sendFilesAction,
    ) {}

    public function sendTextMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $validation = $this->validateTextMessage($request);
        if ($validation->fails()) {
            return $this->errorResponse('Validation Error(s)', $validation->errors()->toArray(), 422);
        }

        $dto = SendWhatsAppMessageDTO::fromRequest($request, $conversation);
        $result = $this->sendTextAction->execute($dto);

        return $this->formatResponse($result, 'Text Message Sent Successfully');
    }

    public function sendImageMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $validation = $this->validateImageMessage($request);
        if ($validation->fails()) {
            return $this->errorResponse('Validation Error(s)', $validation->errors()->toArray(), 422);
        }

        $dto = SendWhatsAppMessageDTO::fromRequest($request, $conversation);
        $result = $this->sendMediaAction->execute($dto);

        return $this->formatResponse($result, 'Image Message Sent Successfully');
    }

    public function sendVideoMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $validation = $this->validateVideoMessage($request);
        if ($validation->fails()) {
            return $this->errorResponse('Validation Error(s)', $validation->errors()->toArray(), 422);
        }

        $dto = SendWhatsAppMessageDTO::fromRequest($request, $conversation);
        $result = $this->sendMediaAction->execute($dto);

        return $this->formatResponse($result, 'Video Message Sent Successfully');
    }

    public function sendAudioMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $validation = $this->validateAudioMessage($request);
        if ($validation->fails()) {
            return $this->errorResponse('Validation Error(s)', $validation->errors()->toArray(), 422);
        }

        $dto = SendWhatsAppMessageDTO::fromRequest($request, $conversation);
        $result = $this->sendMediaAction->execute($dto);

        return $this->formatResponse($result, 'Audio Message Sent Successfully');
    }

    public function sendDocumentMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $validation = $this->validateDocumentMessage($request);
        if ($validation->fails()) {
            return $this->errorResponse('Validation Error(s)', $validation->errors()->toArray(), 422);
        }

        $dto = SendWhatsAppMessageDTO::fromRequest($request, $conversation);
        $result = $this->sendMediaAction->execute($dto);

        return $this->formatResponse($result, 'Document Message Sent Successfully');
    }

    public function sendLocationMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $validation = $this->validateLocationMessage($request);
        if ($validation->fails()) {
            return $this->errorResponse('Validation Error(s)', $validation->errors()->toArray(), 422);
        }

        $dto = SendWhatsAppMessageDTO::fromRequest($request, $conversation);
        $result = $this->sendLocationAction->execute($dto);

        return $this->formatResponse($result, 'Location Message Sent Successfully');
    }

    public function sendReactionMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $validation = $this->validateReactionMessage($request);
        if ($validation->fails()) {
            return $this->errorResponse('Validation Error(s)', $validation->errors()->toArray(), 422);
        }

        $dto = SendWhatsAppMessageDTO::fromRequest($request, $conversation);
        $result = $this->sendReactionAction->execute($dto);

        return $this->formatResponse($result, 'Reaction Sent Successfully');
    }

    public function sendTemplateMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $result = $this->sendTemplateAction->execute($request);
        return $this->formatResponse($result, 'Template Message Sent Successfully');
    }

    public function sendInteractiveMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $result = $this->sendInteractiveAction->execute($request);

        return $this->formatResponse($result, 'Interactive Message Sent Successfully');
    }

    public function sendFlowMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $result = $this->sendFlowAction->execute($request);

        return $this->formatResponse($result, 'Flow Message Sent Successfully');
    }

    public function sendFilesMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $result = $this->sendFilesAction->execute($request);

        return $this->formatFilesResponse($result);
    }

    public function sendStickerMessage(
        Request $request,
        Conversation $conversation
    ): JsonResponse {
        $validation = $this->validateStickerMessage($request);

        if ($validation->fails()) {
            return $this->errorResponse(
                'Validation Error(s)',
                $validation->errors()->toArray(),
                422
            );
        }

        $dto = SendWhatsAppMessageDTO::fromRequest($request, $conversation);

        $result = $this->sendStickerMessageAction->execute($dto);

        return $this->formatResponse(
            $result,
            'Sticker Sent Successfully'
        );
    }



    // ========================================
    // Validation Methods
    // ========================================

    private function validateTextMessage(Request $request): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            'from' => 'required|string|exists:whatsapp_phone_numbers,id',
            'to' => 'required|string',
            'text' => 'required|array',
            'text.body' => 'required|string|max:4096',
            'text.preview_url' => 'nullable|boolean',
        ]);
    }

    private function validateImageMessage(Request $request): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            'from' => 'required|string|exists:whatsapp_phone_numbers,id',
            'to' => 'required|string',
            'image' => 'required|array',
            'image.id' => 'required_without:image.link|string',
            'image.link' => 'required_without:image.id|url',
            'image.caption' => 'nullable|string|max:1024',
        ]);
    }

    private function validateVideoMessage(Request $request): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            'from' => 'required|string|exists:whatsapp_phone_numbers,id',
            'to' => 'required|string',
            'video' => 'required|array',
            'video.id' => 'required_without:video.link|string',
            'video.link' => 'required_without:video.id|url',
            'video.caption' => 'nullable|string|max:1024',
        ]);
    }

    private function validateAudioMessage(Request $request): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            'from' => 'required|string|exists:whatsapp_phone_numbers,id',
            'to' => 'required|string',
            'audio' => 'required|array',
            'audio.id' => 'required_without:audio.link|string',
            'audio.link' => 'required_without:audio.id|url',
        ]);
    }

    private function validateDocumentMessage(Request $request): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            'from' => 'required|string|exists:whatsapp_phone_numbers,id',
            'to' => 'required|string',
            'document' => 'required|array',
            'document.id' => 'required_without:document.link|string',
            'document.link' => 'required_without:document.id|url',
            'document.filename' => 'nullable|string',
            'document.caption' => 'nullable|string|max:1024',
        ]);
    }

    private function validateLocationMessage(Request $request): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            'from' => 'required|string|exists:whatsapp_phone_numbers,id',
            'to' => 'required|string',
            'location' => 'required|array',
            'location.latitude' => 'required|numeric',
            'location.longitude' => 'required|numeric',
            'location.name' => 'nullable|string',
            'location.address' => 'nullable|string',
        ]);
    }

    private function validateReactionMessage(Request $request): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            'from' => 'required|string|exists:whatsapp_phone_numbers,id',
            'to' => 'required|string',
            'reaction' => 'required|array',
            'reaction.message_id' => 'required|string',
            'reaction.emoji' => ['present', 'nullable'],
        ]);
    }


    private function validateStickerMessage(Request $request): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            'from' => 'required|string|exists:whatsapp_phone_numbers,id',
            'to' => 'required|string',
            'sticker' => 'required|array',
            'sticker.id' => 'required_without:sticker.link|string',
            'sticker.link' => 'required_without:sticker.id|url',
        ]);
    }


    // ========================================
    // Response Formatting
    // ========================================

    private function formatResponse(WhatsAppMessageResultDTO $result, string $successMessage): JsonResponse
    {
        if (!$result->success) {
            return $this->errorResponse($result->error, null, $result->statusCode);
        }

        return $this->successResponse(
            $successMessage,
            new ConversationMessage($result->message, Channel::WHATSAPP_PLATFORM)
        );
    }

    private function formatFilesResponse(WhatsAppMessageResultDTO $result): JsonResponse
    {
        if (!$result->success) {
            return $this->errorResponse($result->error, null, $result->statusCode);
        }

        $messages = is_array($result->message)
            ? array_map(fn($msg) => new ConversationMessage($msg, Channel::WHATSAPP_PLATFORM), $result->message)
            : [new ConversationMessage($result->message, Channel::WHATSAPP_PLATFORM)];

        return response()->json([
            'success' => true,
            'message' => 'Files Sent Successfully',
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
