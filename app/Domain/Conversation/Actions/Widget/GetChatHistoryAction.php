<?php

namespace App\Domain\Conversation\Actions\Widget;

use App\Domain\Conversation\Repositories\WidgetRepositoryInterface;
use App\Models\Conversation;
use App\Models\LiveChatMessage;
use App\Models\LiveChatTextMessage;
use App\Models\LiveChatFileMessage;
use App\Models\PreChatFormFieldResponse;
use App\Models\ContactEntity;
use App\Models\Widget;

class GetChatHistoryAction
{
    public function __construct(
        private WidgetRepositoryInterface $widgetRepository,
    ) {}

    public function execute(string $sessionId, ?string $beforeId = null, int $limit = 50): array
    {
        $conversation = Conversation::findOrFail($sessionId);
        $messages = $this->widgetRepository->getChatHistory($sessionId, $beforeId, $limit);

        $formattedMessages = $messages->map(fn($message) => $this->formatMessage($message));

        // Mark visitor messages as read if agent is requesting
        if (auth()->check() && auth()->user()->hasRole('agent')) {
            $this->widgetRepository->markMessagesAsRead($sessionId);
        }

        return [
            'messages' => $formattedMessages,
            'has_more' => count($formattedMessages) >= $limit,
        ];
    }

    private function formatMessage(LiveChatMessage $message, bool $includeRepliedTo = true): array
    {
        $messageContent = $this->getMessageContent($message);
        $senderInfo = $this->getSenderInfo($message);
        $reactions = $message->reactionMessage;

        $result = [
            'id' => $message->id,
            'session_id' => $message->conversation_id,
            'timestamp' => $message->created_at,
            'sender' => $senderInfo,
            'content' => $messageContent,
            'status' => $message->status,
            'is_read' => $message->is_read,
            'read_at' => $message->read_at,
            'reactions' => $reactions->isNotEmpty()
                ? $reactions->map(fn($r) => ['emoji' => $r->emoji, 'direction' => $r->direction])->values()->toArray()
                : null,
        ];

        if ($includeRepliedTo && $message->repliedToMessage) {
            $result['replied_to_message'] = $this->formatMessage($message->repliedToMessage, false);
        }

        return $result;
    }

    private function getMessageContent(LiveChatMessage $message): ?array
    {
        if (!$message->messageable) {
            return null;
        }

        if ($message->messageable_type === LiveChatTextMessage::class) {
            return [
                'type' => 'text',
                'text' => $message->messageable->text,
            ];
        }

        if ($message->messageable_type === LiveChatFileMessage::class) {
            $media = $message->messageable->getFirstMedia('*');
            $type = $this->getFileType($media->mime_type);

            return [
                'type' => $type,
                'file_url' => $type === 'file'
                    ? $message->messageable->getMediaUrl()
                    : $message->messageable->getSignedMediaUrlForPreview(),
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'file_size' => $media->size,
            ];
        }

        if ($message->messageable_type === PreChatFormFieldResponse::class) {
            $fieldResponse = $message->messageable;
            $allResponses = PreChatFormFieldResponse::getConversationResponses($message->conversation_id);

            return [
                'type' => 'pre_chat_form',
                'field_id' => $fieldResponse->field_id,
                'field_name' => optional($fieldResponse->field)->name,
                'field_label' => optional($fieldResponse->field)->label,
                'responses' => $allResponses,
            ];
        }

        return null;
    }

    private function getSenderInfo(LiveChatMessage $message): ?array
    {
        if ($message->sender_type === ContactEntity::class) {
            return [
                'type' => 'visitor',
                'name' => 'Visitor',
            ];
        }

        if ($message->sender_type === Widget::class) {
            return [
                'type' => 'agent',
                'name' => optional($message->agent)->name ?? 'Agent',
                'avatar' => optional($message->sender)->avatar ?? null,
            ];
        }

        return null;
    }

    private function getFileType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }
        return 'file';
    }
}
