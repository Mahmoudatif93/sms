<?php

namespace App\Domain\Conversation\Actions\Widget;

use App\Domain\Conversation\DTOs\Widget\WidgetMessageDTO;
use App\Domain\Conversation\Repositories\WidgetRepositoryInterface;
use App\Domain\Conversation\Repositories\LiveChatMessageRepositoryInterface;
use App\Models\Conversation;
use App\Models\ContactEntity;
use App\Models\LiveChatMessage;
use App\Models\LiveChatTextMessage;
use App\Models\LiveChatFileMessage;

class SendWidgetMessageAction
{
    public function __construct(
        private WidgetRepositoryInterface $widgetRepository,
        private LiveChatMessageRepositoryInterface $messageRepository,
    ) {}

    public function execute(WidgetMessageDTO $dto): array
    {
        $conversation = Conversation::findOrFail($dto->sessionId);
        $widget = $conversation->channel->connector->liveChatConfiguration->widget;

        // Handle pending conversation status
        $this->handlePendingConversation($conversation, $widget);

        // Create message based on type
        $message = $dto->isTextMessage()
            ? $this->createTextMessage($dto, $conversation, $widget)
            : $this->createFileMessage($dto, $conversation, $widget);

        // Save message status
        $this->messageRepository->saveMessageStatus($message->id, 'sent');

        return $this->formatMessageResponse($message);
    }

    private function handlePendingConversation(Conversation $conversation, $widget): void
    {
        if ($conversation->status !== Conversation::STATUS_PENDING) {
            return;
        }

        $preChatForm = $this->widgetRepository->getPreChatForm($conversation->channel_id, $widget->id);

        if (!$preChatForm || !$preChatForm->enabled) {
            $conversation->update([
                'status' => Conversation::STATUS_WAITING,
                'started_at' => now(),
            ]);
        } else {
            throw new \Exception('Pre-chat form must be submitted first');
        }
    }

    private function createTextMessage(WidgetMessageDTO $dto, Conversation $conversation, $widget): LiveChatMessage
    {
        $textMessage = $this->messageRepository->createTextMessage($dto->message);

        return $this->messageRepository->createForConversation($conversation->id, [
            'channel_id' => $conversation->channel_id,
            'widget_id' => $widget->id,
            'sender_type' => ContactEntity::class,
            'sender_id' => $conversation->contact_id,
            'type' => 'text',
            'status' => 'sent',
            'direction' => LiveChatMessage::MESSAGE_DIRECTION_RECEIVED,
            'messageable_type' => get_class($textMessage),
            'messageable_id' => $textMessage->id,
            'replied_to_message_id' => $dto->repliedMessageId,
            'is_read' => false,
        ]);
    }

    private function createFileMessage(WidgetMessageDTO $dto, Conversation $conversation, $widget): LiveChatMessage
    {
        $fileMessage = $this->messageRepository->createFileMessage($dto->caption);

        // Upload file to media collection
        $fileMessage->addMedia($dto->file)->toMediaCollection('livechat_media', 'oss');

        return $this->messageRepository->createForConversation($conversation->id, [
            'channel_id' => $conversation->channel_id,
            'widget_id' => $widget->id,
            'sender_type' => ContactEntity::class,
            'sender_id' => $conversation->contact_id,
            'type' => 'file',
            'direction' => LiveChatMessage::MESSAGE_DIRECTION_RECEIVED,
            'messageable_type' => get_class($fileMessage),
            'messageable_id' => $fileMessage->id,
            'is_read' => false,
            'status' => 'sent',
        ]);
    }

    private function formatMessageResponse(LiveChatMessage $message): array
    {
        $messageContent = $this->getMessageContent($message);

        $response = [
            'id' => $message->id,
            'session_id' => $message->conversation_id,
            'timestamp' => $message->created_at,
            'sender' => [
                'type' => 'visitor',
                'name' => 'Visitor',
            ],
            'content' => $messageContent,
            'status' => $message->status,
            'is_read' => $message->is_read,
            'read_at' => $message->read_at,
            'reactions' => null,
        ];

        if ($message->repliedToMessage) {
            $response['replied_to_message'] = $this->formatRepliedMessage($message->repliedToMessage);
        }

        return $response;
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

    private function formatRepliedMessage($repliedMessage): array
    {
        $repliedContent = null;
        if ($repliedMessage->messageable_type === LiveChatTextMessage::class) {
            $repliedContent = [
                'type' => 'text',
                'text' => $repliedMessage->messageable->text,
            ];
        }

        return [
            'id' => $repliedMessage->id,
            'session_id' => $repliedMessage->conversation_id,
            'timestamp' => $repliedMessage->created_at,
            'content' => $repliedContent,
        ];
    }
}
