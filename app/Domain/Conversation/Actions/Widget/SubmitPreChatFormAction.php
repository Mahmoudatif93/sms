<?php

namespace App\Domain\Conversation\Actions\Widget;

use App\Domain\Conversation\DTOs\Widget\PreChatFormDTO;
use App\Domain\Conversation\Repositories\WidgetRepositoryInterface;
use App\Domain\Conversation\Repositories\LiveChatMessageRepositoryInterface;
use App\Models\Conversation;
use App\Models\ContactEntity;
use App\Models\Identifier;
use App\Models\LiveChatMessage;
use App\Models\LiveChatTextMessage;
use App\Models\Widget;
use App\Models\PreChatFormFieldResponse;
use App\Helpers\Sms\MessageHelper;
use App\Traits\SimpleContactManager;
use App\Traits\LiveChatMessageManager;

class SubmitPreChatFormAction
{
    use SimpleContactManager, LiveChatMessageManager;

    public function __construct(
        private WidgetRepositoryInterface $widgetRepository,
        private LiveChatMessageRepositoryInterface $messageRepository,
    ) {}

    public function execute(PreChatFormDTO $dto): array
    {
        $conversation = Conversation::findOrFail($dto->sessionId);
        $contact = ContactEntity::findOrFail($conversation->contact_id);

        // Update contact with form data
        $this->updateContact_(
            $contact,
            [
                Identifier::EMAIL_KEY => $dto->getEmail(),
                Identifier::PHONE_NUMBER_KEY => $dto->getPhone(),
            ],
            [
                ContactEntity::ATTRIBUTE_NAME => $dto->getName(),
                ContactEntity::ATTRIBUTE_TYPE_DISPALY_NAME => $dto->getName(),
            ]
        );

        // Update conversation status
        $conversation->update([
            'status' => Conversation::STATUS_WAITING,
        ]);

        $widget = $conversation->channel->connector->liveChatConfiguration->widget;

        // Get pre-chat form
        $preChatForm = $this->widgetRepository->getPreChatForm($conversation->channel_id, $widget->id);

        if (!$preChatForm) {
            throw new \Exception('Pre-chat form not found.');
        }

        // Create field responses
        $responses = PreChatFormFieldResponse::createFromFormData(
            $conversation->id,
            $conversation->contact_id,
            $dto->formData,
            $preChatForm
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
            'direction' => 'RECEIVED',
            'type' => 'pre_form_submission',
            'messageable_type' => get_class($firstResponse),
            'messageable_id' => $firstResponse->id,
            'is_read' => false,
        ]);

        $this->messageRepository->saveMessageStatus($message->id, 'delivered');

        // Send notification
        $channelName = $conversation->channel->name ?? null;
        MessageHelper::sendLiveChatNotification($dto->formData, $conversation->id, $channelName);

        // Send welcome message if configured
        $this->sendWelcomeMessage($widget, $conversation);

        return [
            'session' => [
                'id' => $conversation->id,
                'status' => $conversation->status,
            ],
            'message' => $message,
            'conversation' => $conversation,
        ];
    }

    private function sendWelcomeMessage($widget, Conversation $conversation): void
    {
        if (!$widget->welcome_message) {
            return;
        }

        $textMessage = $this->messageRepository->createTextMessage($widget->welcome_message);

        $this->messageRepository->createForConversation($conversation->id, [
            'channel_id' => $conversation->channel_id,
            'widget_id' => $widget->id,
            'sender_type' => Widget::class,
            'sender_id' => $widget->id,
            'type' => 'text',
            'direction' => 'SENT',
            'status' => 'sent',
            'messageable_type' => get_class($textMessage),
            'messageable_id' => $textMessage->id,
            'is_read' => false,
        ]);
    }
}
