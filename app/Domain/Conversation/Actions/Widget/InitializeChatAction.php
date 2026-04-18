<?php

namespace App\Domain\Conversation\Actions\Widget;

use App\Domain\Conversation\DTOs\Widget\InitializeChatDTO;
use App\Domain\Conversation\Repositories\WidgetRepositoryInterface;
use App\Models\Channel;
use App\Models\Conversation;
use App\Traits\SimpleContactManager;
use App\Traits\ConversationManager;

class InitializeChatAction
{
    use SimpleContactManager, ConversationManager;

    public function __construct(
        private WidgetRepositoryInterface $widgetRepository,
    ) {}

    public function execute(InitializeChatDTO $dto): array
    {
        $widget = $this->widgetRepository->findByIdOrFail($dto->widgetId);

        // Check if widget is active
        if (!$widget->is_active) {
            throw new \Exception('This chat widget is not active');
        }

        // Check domain restrictions
        $this->validateDomain($widget, $dto->referrer);

        // Get channel for widget
        $channel = $this->widgetRepository->getChannelForWidget($widget->id);

        if (!$channel) {
            throw new \Exception(__('messages.no_channel_configured'));
        }

        // Find or create contact
        $contact = null;
        if ($channel->organization()?->id) {
            $contact = $this->findOrCreateContact_(
                $channel->organization()->id,
                ['fingerprint' => $dto->fingerprint],
                $dto->getVisitorData()
            );
        }

        // Get or create conversation
        $activeConversation = $this->widgetRepository->getActiveConversation(
            $contact->id,
            Channel::LIVECHAT_PLATFORM,
            $channel
        );

        if (!$activeConversation) {
            $workspace = $channel->defaultWorkspace ?? $channel->workspaces()->first();
            $activeConversation = $this->startConversation(
                Channel::LIVECHAT_PLATFORM,
                $channel,
                $contact,
                "",
                null,
                Conversation::STATUS_PENDING,
                $workspace->id
            );
        }

        // Get forms
        $preChatForm = $this->widgetRepository->getPreChatForm($channel->id, $widget->id);
        $postChatForm = $this->widgetRepository->getPostChatForm($channel->id, $widget->id);

        return $this->buildResponse($widget, $activeConversation, $contact, $preChatForm, $postChatForm, $channel);
    }

    private function validateDomain($widget, ?string $referrer): void
    {
        if (empty($widget->allowed_domains)) {
            return;
        }

        $allowedDomainsRaw = json_decode($widget->allowed_domains, true);
        $allowedHosts = array_map(fn($domain) => parse_url($domain, PHP_URL_HOST), $allowedDomainsRaw);
        $referrerHost = parse_url($referrer, PHP_URL_HOST);

        if (!in_array($referrerHost, $allowedHosts)) {
            throw new \Exception(__('messages.chat_widget_not_allowed'));
        }
    }

    private function buildResponse($widget, $conversation, $contact, $preChatForm, $postChatForm, $channel): array
    {
        $preChatEnabled = $preChatForm && $preChatForm->enabled;
        $postChatEnabled = $postChatForm && $postChatForm->enabled;

        return [
            'widget' => [
                'id' => $widget->id,
                'theme_color' => $widget->theme_color,
                'logo_url' => $widget->logo_url,
                'welcome_message' => $widget->welcome_message,
                'offline_message' => $widget->offline_message,
                'message_placeholder' => $widget->message_placeholder,
                'show_agent_avatar' => $widget->show_agent_avatar,
                'show_agent_name' => $widget->show_agent_name,
                'show_file_upload' => $widget->show_file_upload,
                'position' => $widget->position,
                'language' => $widget->language,
                'sound_enabled' => $widget->sound_enabled,
                'auto_open' => $widget->auto_open,
                'auto_open_delay' => $widget->auto_open_delay,
            ],
            'session' => [
                'id' => $conversation->id,
                'status' => $conversation->status,
                'is_continuation' => in_array($conversation->status, [
                    Conversation::STATUS_WAITING,
                    Conversation::STATUS_ACTIVE,
                    Conversation::STATUS_OPEN,
                ]),
            ],
            'contact' => [
                'id' => $contact->id,
                'name' => 'visitor',
            ],
            'pre_chat_form' => $preChatEnabled ? $this->formatForm($preChatForm) : ['enabled' => false],
            'post_chat_form' => $postChatEnabled ? $this->formatForm($postChatForm) : ['enabled' => false],
            'has_previous_conversations' => $this->widgetRepository->hasEndedConversations(
                $contact->id,
                Channel::LIVECHAT_PLATFORM,
                $channel
            ),
        ];
    }

    private function formatForm($form): array
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
