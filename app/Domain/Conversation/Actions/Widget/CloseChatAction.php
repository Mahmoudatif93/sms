<?php

namespace App\Domain\Conversation\Actions\Widget;

use App\Domain\Conversation\Repositories\WidgetRepositoryInterface;
use App\Domain\Conversation\Repositories\LiveChatMessageRepositoryInterface;
use App\Models\Conversation;
use App\Models\ContactEntity;
use App\Models\LiveChatMessage;

class CloseChatAction
{
    public function __construct(
        private WidgetRepositoryInterface $widgetRepository,
        private LiveChatMessageRepositoryInterface $messageRepository,
    ) {}

    public function execute(string $sessionId): array
    {
        $conversation = Conversation::findOrFail($sessionId);
        $widget = $conversation->channel->connector->liveChatConfiguration->widget;

        // Validate status
        $validStatuses = [
            Conversation::STATUS_ACTIVE,
            Conversation::STATUS_WAITING,
            Conversation::STATUS_OPEN,
        ];

        if (!in_array($conversation->status, $validStatuses)) {
            throw new \Exception('Cannot close a conversation that is not active or waiting');
        }

        // Update conversation status
        $conversation->update([
            'status' => Conversation::STATUS_ARCHIVED,
            'closed_at' => now(),
        ]);

        // Create closing message
        $textMessage = $this->messageRepository->createTextMessage('Archived - customer left the chat');

        $message = $this->messageRepository->createForConversation($conversation->id, [
            'channel_id' => $conversation->channel_id,
            'widget_id' => $widget->id,
            'sender_type' => ContactEntity::class,
            'sender_id' => $conversation->contact_id,
            'type' => 'text',
            'status' => 'sent',
            'messageable_type' => get_class($textMessage),
            'messageable_id' => $textMessage->id,
            'is_read' => false,
        ]);

        $this->messageRepository->saveMessageStatus($message->id, 'sent');

        // Get post-chat form
        $postChatForm = $this->widgetRepository->getPostChatForm($conversation->channel_id, $widget->id);
        $postChatEnabled = $postChatForm && $postChatForm->enabled;

        return [
            'session' => [
                'id' => $conversation->id,
                'status' => $conversation->status,
            ],
            'post_chat_form' => $postChatEnabled ? $this->formatPostChatForm($postChatForm) : ['enabled' => false],
            'message' => $message,
            'conversation' => $conversation,
        ];
    }

    private function formatPostChatForm($form): array
    {
        return [
            'enabled' => true,
            'id' => $form->id,
            'title' => $form->title,
            'description' => $form->description,
            'submit_button_text' => $form->submit_button_text,
            'fields' => $form->fields()->get()->map(fn($field) => [
                'id' => $field->id,
                'type' => $field->type,
                'name' => $field->name,
                'label' => $field->label,
                'placeholder' => $field->placeholder,
                'required' => $field->required,
                'options' => $field->options,
                'validation' => $field->validation,
                'order' => $field->order,
            ]),
        ];
    }
}
