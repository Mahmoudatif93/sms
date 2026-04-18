<?php

namespace App\Domain\Conversation\Channels;

use App\Domain\Conversation\DTOs\LiveChatMessageResultDTO;
use App\Domain\Conversation\DTOs\MessageResultDTO;
use App\Domain\Conversation\DTOs\WhatsAppMessageResultDTO;
use App\Domain\Messenger\DTOs\MessengerMessageResultDTO;
use App\Http\Responses\ConversationMessage;
use App\Models\Channel;
use App\Models\Conversation;
use App\Services\LanguageDetectionService;
use App\Traits\ResponseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

abstract class AbstractChannel implements ChannelInterface
{
    use ResponseManager;

    /**
     * Default supported message types (can be overridden)
     */
    protected array $supportedMessageTypes = ['text', 'files'];

    /**
     * Send a message - routes to appropriate method based on type
     */
    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $messageType = $request->input('type');

        if (!$this->supportsMessageType($messageType)) {
            return $this->errorResponse(
                "Message type '{$messageType}' is not supported by {$this->getPlatform()} channel",
                null,
                400
            );
        }

        return match ($messageType) {
            'text' => $this->sendTextMessage($request, $conversation),
            'files', 'file' => $this->sendFileMessage($request, $conversation),
            'reaction' => $this->sendReactionMessage($request, $conversation),
            'image' => $this->sendImageMessage($request, $conversation),
            'video' => $this->sendVideoMessage($request, $conversation),
            'audio' => $this->sendAudioMessage($request, $conversation),
            'document' => $this->sendDocumentMessage($request, $conversation),
            'location' => $this->sendLocationMessage($request, $conversation),
            'template' => $this->sendTemplateMessage($request, $conversation),
            'interactive' => $this->sendInteractiveMessage($request, $conversation),
            'flow' => $this->sendFlowMessage($request, $conversation),
            default => $this->unsupportedTypeResponse($messageType),
        };
    }

    /**
     * Check if message type is supported
     */
    public function supportsMessageType(string $type): bool
    {
        return in_array($type, $this->getSupportedMessageTypes());
    }

    /**
     * Get list of supported message types
     */
    public function getSupportedMessageTypes(): array
    {
        return $this->supportedMessageTypes;
    }

    /**
     * Default implementations for optional message types (can be overridden)
     */
    public function sendImageMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->unsupportedTypeResponse('image');
    }

    public function sendVideoMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->unsupportedTypeResponse('video');
    }

    public function sendAudioMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->unsupportedTypeResponse('audio');
    }

    public function sendDocumentMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->unsupportedTypeResponse('document');
    }

    public function sendLocationMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->unsupportedTypeResponse('location');
    }

    public function sendTemplateMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->unsupportedTypeResponse('template');
    }

    public function sendInteractiveMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->unsupportedTypeResponse('interactive');
    }

    public function sendFlowMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->unsupportedTypeResponse('flow');
    }

    public function sendReactionMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->unsupportedTypeResponse('reaction');
    }

    /**
     * Default mark as delivered - can be overridden
     */
    public function markAsDelivered(Conversation $conversation): JsonResponse
    {
        return $this->errorResponse('Mark as delivered not supported for ' . $this->getPlatform(), null, 400);
    }

    /**
     * Default handle close - can be overridden
     */
    public function handleClose(Conversation $conversation, string $closedBy): void
    {
        // Default implementation does nothing
    }

    /**
     * Default handle reopen - can be overridden
     */
    public function handleReopen(Conversation $conversation): void
    {
        // Default implementation does nothing
    }

    /**
     * Log channel activity
     */
    protected function log(string $message, array $context = []): void
    {
        Log::channel('conversations')->info("[{$this->getPlatform()}] {$message}", $context);
    }

    /**
     * Log channel error
     */
    protected function logError(string $message, array $context = []): void
    {
        Log::channel('conversations')->error("[{$this->getPlatform()}] {$message}", $context);
    }

    /**
     * Return unsupported type response
     */
    protected function unsupportedTypeResponse(string $type): JsonResponse
    {
        return $this->errorResponse(
            "Message type '{$type}' is not supported by {$this->getPlatform()} channel",
            null,
            400
        );
    }

    /**
     * Create a JSON response (used by channels)
     */
    protected function response(bool $success, string $message, mixed $data = null, int $statusCode = 200): JsonResponse
    {
        return new JsonResponse([
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Get channel configuration from conversation
     */
    protected function getChannelConfiguration(Conversation $conversation)
    {
        return $conversation->channel?->connector;
    }

    /**
     * Update conversation status to open
     */
    protected function activateConversation(Conversation $conversation): void
    {
        if ($conversation->status !== Conversation::STATUS_OPEN) {
            $conversation->status = Conversation::STATUS_OPEN;
            $conversation->save();
        }
    }

    /**
     * Translate outgoing message if auto-translation is enabled
     */
    protected function translateOutgoingMessage(Request $request, Conversation $conversation): void
    {
        try {
            $messageType = $request->input('type');
            if ($messageType !== 'text') {
                return;
            }

            $targetLanguage = $conversation->detected_language;
            if (!$targetLanguage) {
                return;
            }

            // Get text body based on platform format
            $textBody = $request->input('text.body') ?? $request->input('message');
            if (!$textBody) {
                return;
            }

            $organization = $conversation->workspace->organization ?? null;
            if (!$organization || !$organization->isAutoTranslationEnabled()) {
                return;
            }

            $languageDetectionService = app(LanguageDetectionService::class);
            $sourceLanguage = $languageDetectionService->detect($textBody);

            if ($sourceLanguage && $sourceLanguage === $targetLanguage) {
                return;
            }

            $translatedText = $this->translateText($conversation->workspace, $textBody, $targetLanguage);

            if ($translatedText && $translatedText !== $textBody) {
                // Merge translated text based on platform format
                if ($request->has('text.body')) {
                    $request->merge([
                        'text' => [
                            'body' => $translatedText,
                            'preview_url' => $request->input('text.preview_url'),
                        ],
                        'original_text' => $textBody,
                        'translated_to' => $targetLanguage,
                    ]);
                } else {
                    $request->merge([
                        'message' => $translatedText,
                        'original_text' => $textBody,
                        'translated_to' => $targetLanguage,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to translate outgoing message', [
                'conversation_id' => $conversation->id,
                'platform' => $this->getPlatform(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Translate text to target language using TranslationService
     */
    protected function translateText($workspace, string $text, string $targetLanguage): ?string
    {
        try {
            return app(\App\Services\TranslationService::class)->translate($text, $targetLanguage);
        } catch (\Exception $e) {
            Log::error('Translation service failed', [
                'platform' => $this->getPlatform(),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    // ========================================
    // Response Formatting Methods
    // ========================================

    /**
     * Format a single message result DTO to JsonResponse
     */
    protected function formatMessageResponse(
        WhatsAppMessageResultDTO|MessengerMessageResultDTO|LiveChatMessageResultDTO $result,
        string $successMessage
    ): JsonResponse {
        if (!$result->success) {
            return $this->errorResponse($result->error, null, $result->statusCode);
        }

        return $this->successResponse(
            $successMessage,
            new ConversationMessage($result->message, $this->getPlatform())
        );
    }

    /**
     * Format a files/multiple messages result DTO to JsonResponse
     */
    protected function formatFilesResponse(
        WhatsAppMessageResultDTO|MessengerMessageResultDTO|LiveChatMessageResultDTO $result,
        string $successMessage = 'Files sent successfully'
    ): JsonResponse {
        if (!$result->success) {
            return $this->errorResponse($result->error, $result->errors, $result->statusCode);
        }

        $messages = is_array($result->message)
            ? array_map(fn($msg) => new ConversationMessage($msg, $this->getPlatform()), $result->message)
            : [new ConversationMessage($result->message, $this->getPlatform())];

        return $this->jsonResponse(true, $successMessage, [
            'messages' => $messages,
            'errors' => $result->errors,
        ]);
    }
}
