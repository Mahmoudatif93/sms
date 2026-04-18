<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;
use App\Models\LiveChatMessage;
use App\Models\User;
use App\Models\WhatsappFlowMessage;
use App\Models\WhatsappFlowResponseMessage;
use App\Models\WhatsappMessage;
use App\Traits\ContactManager;
use App\Traits\WhatsappTemplateManager;

class Conversation extends DataInterface
{
    use ContactManager, WhatsappTemplateManager;

    /**
     * Conversation ID.
     *
     * @var string
     */
    public string $id;

    /**
     * The messaging platform (WhatsApp, SMS, etc.).
     *
     * @var string
     */
    public string $platform;

    /**
     * The channel associated with the conversation.
     *
     * @var string
     */
    public string $channel_id;

    /**
     * The display name of the contact.
     *
     * @var string|null
     */
    public ?string $contact_display;

    /**
     * The last message content in the conversation.
     *
     * @var string|null
     */
    public ?string $last_message;

    /**
     * The type of the last message.
     *
     * @var string|null
     */
    public ?string $last_message_type;

    /**
     * Timestamp of the last message.
     *
     * @var string|null
     */
    public ?string $timestamp;

    /**
     * Count of unread notifications in the conversation.
     *
     * @var int
     */
    public int $unread_notifications_count;

    public ?InboxAgent $inboxAgent;

    public ?int $customer_service_window_ends_at;
    public ?string $workspcae_id;
    public ?Contact $contact;

    /**
     * Construct the Conversation Response.
     *
     * @param \App\Models\Conversation $conversation
     */
    public function __construct(\App\Models\Conversation $conversation)
    {
        $this->id = $conversation->id;
        $this->platform = $conversation->platform;
        $this->channel_id = $conversation->channel_id;
        $this->workspcae_id = $conversation->workspace_id;
        $this->contact_display = $this->getContactName($conversation->contact, $conversation->platform);
        $this->contact = $conversation->contact ? new Contact($conversation->contact) : null;

        // Use eager-loaded latest message relations instead of new query
        $lastMessage = $this->getLatestMessage($conversation);

        $this->last_message = $this->getFormattedLastMessage($lastMessage, $this->platform);
        $this->last_message_type = $lastMessage?->type ?? null;

        // Use last_message_at if available, fallback to message timestamp or updated_at
        $this->timestamp = $conversation->last_message_at
            ?? $lastMessage?->created_at
            ?? $conversation->updated_at;

        // Customer Service Window Ends At
        $this->customer_service_window_ends_at = $conversation->customerServiceWindowEndsAt();

        // Count unread messages from eager-loaded relations
        $this->unread_notifications_count = $this->countUnreadMessages($conversation);

        $currentAgent = $conversation->currentAgent();
        $this->inboxAgent = $currentAgent instanceof User ? new InboxAgent($currentAgent) : null;
    }

    /**
     * Get the latest message from eager-loaded relations
     */
    private function getLatestMessage(\App\Models\Conversation $conversation)
    {
        // Get all loaded latest messages
        $messages = collect([
            $conversation->relationLoaded('latestWhatsappMessage') ? $conversation->latestWhatsappMessage : null,
            $conversation->relationLoaded('latestMessengerMessage') ? $conversation->latestMessengerMessage : null,
            $conversation->relationLoaded('latestLiveChatMessage') ? $conversation->latestLiveChatMessage : null,
        ])->filter();

        // If no relations loaded, fallback to query (for backward compatibility)
        if ($messages->isEmpty()) {
            return $conversation->messages()?->latest()?->first();
        }

        // Return the most recent message
        return $messages->sortByDesc('created_at')->first();
    }

    /**
     * Count unread messages from eager-loaded relations or query
     */
    private function countUnreadMessages(\App\Models\Conversation $conversation): int
    {
        // If messages relation is loaded, count from it
        if ($conversation->relationLoaded('whatsappMessages')) {
            return $conversation->whatsappMessages
                ->where('status', '!=', LiveChatMessage::MESSAGE_STATUS_READ)
                ->where('direction', WhatsappMessage::MESSAGE_DIRECTION_RECEIVED)
                ->count();
        }

        if ($conversation->relationLoaded('liveChatMessages')) {
            return $conversation->liveChatMessages
                ->where('status', '!=', LiveChatMessage::MESSAGE_STATUS_READ)
                ->where('direction', WhatsappMessage::MESSAGE_DIRECTION_RECEIVED)
                ->count();
        }

        if ($conversation->relationLoaded('messengerMessages')) {
            return $conversation->messengerMessages
                ->where('status', '!=', LiveChatMessage::MESSAGE_STATUS_READ)
                ->where('direction', WhatsappMessage::MESSAGE_DIRECTION_RECEIVED)
                ->count();
        }

        // Fallback to query
        return $conversation->messages()
            ?->where('status', '!=', LiveChatMessage::MESSAGE_STATUS_READ)
            ->where('direction', '=', WhatsappMessage::MESSAGE_DIRECTION_RECEIVED)
            ->count() ?? 0;
    }

    public function getFormattedLastMessage($message, $platform)
    {
        // Default message content
        $lastMessageContent = null;

        // Check platform
        if ($platform === 'whatsapp') {
            // Handle WhatsApp-specific message formatting
            if ($message) {
                $lastMessageContent = match ($message->type) {
                    WhatsappMessage::MESSAGE_TYPE_IMAGE => 'Sent an image',
                    WhatsappMessage::MESSAGE_TYPE_VIDEO => 'Sent a video',
                    WhatsappMessage::MESSAGE_TYPE_AUDIO => 'Sent an audio message',
                    WhatsappMessage::MESSAGE_TYPE_LOCATION => 'Sent a location',
                    WhatsappMessage::MESSAGE_TYPE_DOCUMENT => 'Sent a document',
                    WhatsappMessage::MESSAGE_TYPE_TEMPLATE => $this->getTemplateBodyWithParameters($message),
                    WhatsappMessage::MESSAGE_TYPE_TEXT => $message->messageable?->body,
                    WhatsappMessage::MESSAGE_TYPE_REACTION => $message?->messageable?->emoji,
                    WhatsappMessage::MESSAGE_TYPE_INTERACTIVE => match (true) {
                        $message->messageable instanceof WhatsappFlowMessage => 'Sent a flow message',
                        $message->messageable instanceof WhatsappFlowResponseMessage => 'Responded to a flow',
                        default => 'Sent an interactive message',
                    },

                    default => $message->messageable ?? 'Unknown message type',
                };
            }
        } else {
            $lastMessage = $message?->messageable()->latest()->first();
            // For other platforms, handle non-WhatsApp message types
            $lastMessageContent = $lastMessage?->text ?? ($lastMessage?->file?->url ?? 'No content available');
        }

        return $lastMessageContent;
    }


}
