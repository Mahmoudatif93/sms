<?php

namespace App\Domain\Conversation\Actions\LiveChat;

use App\Domain\Conversation\DTOs\LiveChatMessageResultDTO;
use App\Domain\Conversation\Repositories\LiveChatMessageRepositoryInterface;
use App\Models\Conversation;
use App\Models\LiveChatFileMessage;
use App\Models\LiveChatMessage;
use App\Models\Widget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SendFileMessageAction
{
    public function __construct(
        private LiveChatMessageRepositoryInterface $repository,
    ) {}

    public function execute(
        Request $request,
        Conversation $conversation,
        string $widgetId
    ): LiveChatMessageResultDTO {
        try {
            $files = $request->file('files');
            $filesData = $request->input('files', []);
            $replyToMessageId = $request->input('reply_to_message_id');

            $sentMessages = [];
            $errors = [];

            foreach ($files as $index => $fileData) {
                $result = $this->sendSingleFile(
                    $conversation,
                    $widgetId,
                    $index,
                    $fileData,
                    $filesData[$index]['caption'] ?? null,
                    $replyToMessageId
                );

                if ($result['success']) {
                    $sentMessages[] = $result['message'];
                } else {
                    $errors[] = $result['error'];
                }
            }

            if (empty($sentMessages)) {
                return LiveChatMessageResultDTO::failure(
                    'All files failed to send: ' . implode('; ', $errors),
                    500
                );
            }

            return LiveChatMessageResultDTO::success($sentMessages, $errors);

        } catch (\Exception $e) {
            Log::error('LiveChat SendFileMessageAction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return LiveChatMessageResultDTO::failure('An error occurred: ' . $e->getMessage(), 500);
        }
    }

    private function sendSingleFile(
        Conversation $conversation,
        string $widgetId,
        int $index,
        array $fileData,
        ?string $caption,
        ?string $replyToMessageId
    ): array {
        try {
            $file = $fileData['file'] ?? null;
            if (!$file) {
                return ['success' => false, 'error' => "File at index {$index} is missing"];
            }

            // Create file message content
            $fileMessage = $this->repository->createFileMessage($caption);

            // Upload file to media collection
            $fileMessage
                ->addMedia($file)
                ->toMediaCollection('livechat_media', 'oss');

            // Create main message via Repository
            $message = $this->repository->createForConversation($conversation->id, [
                'channel_id' => $conversation->channel_id,
                'workspace_id' => $conversation->workspace_id,
                'widget_id' => $widgetId,
                'type' => 'file',
                'status' => LiveChatMessage::MESSAGE_STATUS_SENT,
                'agent_id' => auth('api')->id(),
                'direction' => LiveChatMessage::MESSAGE_STATUS_SENT,
                'sender_type' => Widget::class,
                'sender_id' => $widgetId,
                'messageable_type' => LiveChatFileMessage::class,
                'messageable_id' => $fileMessage->id,
                'is_read' => false,
                'replied_to_message_id' => $replyToMessageId,
            ]);

            // Save message status via Repository
            $this->repository->saveMessageStatus($message->id, 'sent');

            return ['success' => true, 'message' => $message];

        } catch (\Exception $e) {
            Log::error("Failed to send file at index {$index}", [
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => "Exception at index {$index}: " . $e->getMessage()];
        }
    }
}
