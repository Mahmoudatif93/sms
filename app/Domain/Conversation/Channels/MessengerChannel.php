<?php

namespace App\Domain\Conversation\Channels;

use App\Domain\Messenger\DTOs\SendTextMessageDTO;
use App\Domain\Messenger\Services\MessengerMessageService;
use App\Models\Channel;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessengerChannel extends AbstractChannel
{
    protected array $supportedMessageTypes = [
        'text',
        'image',
        'video',
        'audio',
        'file',
        'files',
        'document',
    ];

    public function __construct(
        private MessengerMessageService $messageService
    ) {}

    public function getPlatform(): string
    {
        return Channel::MESSENGER_PLATFORM;
    }

    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $channel = $conversation->channel;
        $connector = $channel->connector;
        $messengerConfiguration = $connector->messengerConfiguration;

        if (!$messengerConfiguration || !$messengerConfiguration->meta_page_id) {
            return $this->errorResponse('Messenger Configuration is missing or incomplete', null, 400);
        }

        $request->merge(['from' => (string) $messengerConfiguration->meta_page_id]);

        $contact = $conversation->contact;
        $messengerConsumer = $contact->messengerConsumers()->first();

        if (!$messengerConsumer) {
            return $this->errorResponse('Messenger consumer not found for this contact', null, 400);
        }

        $request->merge(['to' => $messengerConsumer->psid]);

        // Normalize document type to file (Messenger uses 'file' for documents)
        $this->normalizeDocumentType($request);

        return parent::sendMessage($request, $conversation);
    }

    /**
     * Normalize document type to file for Messenger API compatibility.
     * Messenger API uses 'file' type for documents, unlike WhatsApp which uses 'document'.
     * Preserves original type in 'storage_type' for database storage.
     */
    private function normalizeDocumentType(Request $request): void
    {
        $type = $request->input('type');

        // Convert top-level document type to file, preserve original for storage
        if ($type === 'document') {
            $request->merge([
                'type' => 'file',
                'storage_type' => 'document',
            ]);
        }

        // Convert document type within files array, preserve original for storage
        if ($request->has('files')) {
            $files = $request->input('files');
            foreach ($files as $index => $file) {
                if (isset($file['type']) && $file['type'] === 'document') {
                    $files[$index]['storage_type'] = 'document';
                    $files[$index]['type'] = 'file';
                }
            }
            $request->merge(['files' => $files]);
        }
    }

    public function sendTextMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $dto = new SendTextMessageDTO(
            pageId: $request->input('from'),
            recipientPsid: $request->input('to'),
            text: $request->input('text.body', $request->input('text')),
            conversationId: $conversation->id,
            replyToMessageId: $request->input('context.message_id'),
        );

        $result = $this->messageService->sendTextMessage($dto, $conversation);

        return $this->formatMessageResponse($result, 'Text message sent successfully');
    }

    public function sendFileMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $result = $this->messageService->sendFilesMessage(
            $request,
            $request->input('from'),
            $request->input('to'),
            $conversation->id,
            $request->input('context.message_id')
        );

        return $this->formatFilesResponse($result, 'File(s) sent successfully');
    }

    public function sendImageMessage(Request $request, Conversation $conversation): JsonResponse
    {
        // Redirect to sendFileMessage as Messenger handles all attachments the same way
        return $this->sendFileMessage($request, $conversation);
    }

    public function sendVideoMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->sendFileMessage($request, $conversation);
    }

    public function sendAudioMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->sendFileMessage($request, $conversation);
    }

    public function sendDocumentMessage(Request $request, Conversation $conversation): JsonResponse
    {
        // Document is already normalized to file in sendMessage()
        return $this->sendFileMessage($request, $conversation);
    }

    public function markAsRead(Conversation $conversation): JsonResponse
    {
        $result = $this->messageService->markAsRead($conversation);

        if (!$result['success']) {
            return $this->errorResponse($result['error'], null, 400);
        }

        return $this->successResponse(
            $result['message'],
            ['marked_count' => $result['marked_count']]
        );
    }
}
