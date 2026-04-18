<?php

namespace App\Domain\Conversation\Actions\Widget;

use App\Domain\Conversation\DTOs\Widget\PostChatFormDTO;
use App\Domain\Conversation\Repositories\WidgetRepositoryInterface;
use App\Domain\Conversation\Repositories\LiveChatMessageRepositoryInterface;
use App\Models\Conversation;
use App\Models\ContactEntity;
use App\Models\PostChatFormFieldResponse;

class SubmitPostChatFormAction
{
    public function __construct(
        private WidgetRepositoryInterface $widgetRepository,
        private LiveChatMessageRepositoryInterface $messageRepository,
    ) {}

    public function execute(PostChatFormDTO $dto): array
    {
        $conversation = Conversation::findOrFail($dto->sessionId);

        // Validate conversation status
        $validStatuses = [
            Conversation::STATUS_CLOSED,
            Conversation::STATUS_ARCHIVED,
            Conversation::STATUS_ENDED,
        ];

        if (!in_array($conversation->status, $validStatuses)) {
            throw new \Exception(__('messages.post_chat_form_invalid'));
        }

        $widget = $conversation->channel->connector->liveChatConfiguration->widget;
        $postChatForm = $this->widgetRepository->getPostChatForm($conversation->channel_id, $widget->id);

        if (!$postChatForm) {
            throw new \Exception('Post-chat form not found');
        }

        // Validate required fields
        $this->validateRequiredFields($postChatForm, $dto->formData);

        // Create field responses
        $responses = PostChatFormFieldResponse::createFromFormData(
            $conversation->id,
            $conversation->contact_id,
            $dto->formData,
            $postChatForm
        );

        $firstResponse = $responses[0] ?? null;

        if (!$firstResponse) {
            throw new \Exception('Failed to create form responses.');
        }

        // Create message record
        $message = $this->messageRepository->createForConversation($conversation->id, [
            'channel_id' => $conversation->channel_id,
            'widget_id' => $widget->id,
            'sender_type' => ContactEntity::class,
            'sender_id' => $conversation->contact_id,
            'type' => 'post_form_submission',
            'messageable_type' => get_class($firstResponse),
            'messageable_id' => $firstResponse->id,
            'is_read' => false,
        ]);

        $this->messageRepository->saveMessageStatus($message->id, 'sent');

        return [
            'session' => [
                'id' => $conversation->id,
                'status' => $conversation->status,
            ],
            'message' => $message,
            'conversation' => $conversation,
        ];
    }

    private function validateRequiredFields($postChatForm, array $formData): void
    {
        if (!$postChatForm->require_fields) {
            return;
        }

        $requiredFields = $postChatForm->fields()
            ->where('required', true)
            ->where('enabled', true)
            ->get();

        foreach ($requiredFields as $field) {
            if (!isset($formData[$field->name]) || empty($formData[$field->name])) {
                throw new \Exception("Field '{$field->label}' is required");
            }
        }
    }
}
