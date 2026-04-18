<?php

namespace App\Services\Messaging;

use App\Events\UnifiedMessageEvent;
use App\Models\LiveChatMessage;
use App\Models\Conversation;
use App\Models\Channel;
use App\Models\ConversationAgent;
use App\Models\LiveChatTextMessage;
use App\Models\LiveChatFileMessage;
use App\Models\LiveChatConfiguration;
use App\Models\Widget;

class LiveChatMessageHandler
{
    /**
     * Handle a new incoming LiveChat message.
     *
     * @param LiveChatMessage $message
     * @param Conversation|App\Http\Responses\Conversation $conversation
     * @return void
     */
    public function handleIncomingMessage($message, $conversation)
    {
        // Transform LiveChat message data into a standardized payload
        $payload = [
            'message_id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'conversation' => [
                'data' => new \App\Http\Responses\Conversation($conversation)
            ],
            'message' => [
                'data' => new \App\Http\Responses\ConversationMessage($message, Channel::LIVECHAT_PLATFORM)
            ]
        ];

        // Dispatch the unified event
        event(new UnifiedMessageEvent(
            Channel::LIVECHAT_PLATFORM,
            'new-message',
            $payload,
            $message->channel_id,
            null,
            $conversation->workspace_id
        ));
    }

    public function handleAgentIncomingMessage($message, $conversation)
    {

        $senderInfo = [
            'type' => 'agent',
            'name' => optional($message->agent)->name ?? 'Agent',
            'avatar' => optional($message->sender)->avatar ?? null,
        ];
        // Transform LiveChat message data into a standardized payload
        if ($message->messageable instanceof LiveChatTextMessage) {
            $content = [
                'text' => $message->messageable->text,
                'type' => 'text'
            ];

        } elseif ($message->messageable instanceof LiveChatFileMessage) {
            $media = $message->messageable->getFirstMedia('*');
            $content = [
                'type' => 'file',
                'file_url' => $message->messageable->getSignedMediaUrlForPreview(),
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'file_size' => $media->size,
            ];

        } else {
            $content = [
                'type' => $message->messageable_type,
                'content' => $message->messageable ? $message->messageable->toArray() : []
            ];
        }

        $payload = [
            'id' => $message->id,
            'session_id' => $message->conversation_id,
            'sender' => $senderInfo,
            'timestamp' => $message->created_at,
            'content' => $content,
            'status' => $message->status,
            'is_read' => false,
            'read_at' => null
        ];

        // Add replied_to_message if exists
        if ($message->replied_to_message_id) {
            $repliedMessage = LiveChatMessage::find($message->replied_to_message_id);
            if ($repliedMessage) {
                $repliedSenderInfo = [
                    'type' => $repliedMessage->sender_type === 'App\Models\Widget' ? 'visitor' : 'agent',
                    'name' => $repliedMessage->sender_type === 'App\Models\Widget' ? 'Visitor' : (optional($repliedMessage->agent)->name ?? 'Agent'),
                    'avatar' => $repliedMessage->sender_type !== 'App\Models\Widget' ? optional($repliedMessage->sender)->avatar : null,
                ];

                $repliedContent = [];
                if ($repliedMessage->messageable instanceof LiveChatTextMessage) {
                    $repliedContent = [
                        'text' => $repliedMessage->messageable->text,
                        'type' => 'text'
                    ];
                } elseif ($repliedMessage->messageable instanceof LiveChatFileMessage) {
                    $repliedMedia = $repliedMessage->messageable->getFirstMedia('*');
                    $repliedContent = [
                        'type' => 'file',
                        'file_url' => $repliedMessage->messageable->getSignedMediaUrlForPreview(),
                        'file_name' => $repliedMedia->file_name,
                        'mime_type' => $repliedMedia->mime_type,
                        'file_size' => $repliedMedia->size,
                    ];
                }

                $payload['replied_to_message'] = [
                    'id' => $repliedMessage->id,
                    'sender' => $repliedSenderInfo,
                    'content' => $repliedContent,
                    'timestamp' => $repliedMessage->created_at,
                ];
            }
        }
        // Dispatch the unified event
        event(new UnifiedMessageEvent(
            Channel::LIVECHAT_PLATFORM,
            'new-message',
            $payload,
            $message->channel_id,
            $message->conversation_id,
            $conversation->workspace_id
        ));
    }



    /**
     * Handle a LiveChat status update.
     *
     * @param Conversation $conversation
     * @param string $oldStatus
     * @param string $newStatus
     * @return void
     */
    public function handleStatusUpdate(LiveChatMessage $message, string $status)
    {
        $payload = [
            'message_id' => $message->id,
            'old_status' => $message->status,
            'new_status' => $status,
            'status' => $status, // For consistency with other status updates
            'timestamp' => now(),
            'conversation_id' => $message->conversation_id
        ];

        // Dispatch the unified event
        event(new UnifiedMessageEvent(
            'livechat',
            'status-update',
            $payload,
            $message->channel_id,
            null,
            $message->conversation->workspace_id
        ));
    }

    public function handleAgentStatusUpdate(LiveChatMessage $message, string $status)
    {
        $payload = [
            'message_id' => $message->id,
            'old_status' => $message->status,
            'new_status' => $status,
            'status' => $status, // For consistency with other status updates
            'timestamp' => now(),
            'conversation_id' => $message->conversation_id,
            'replied_message_id' => $message->replied_to_message_id
        ];

        // Dispatch the unified event
        event(new UnifiedMessageEvent(
            'livechat',
            'status-update',
            $payload,
            $message->channel_id,
            $message->conversation_id,
            $message->conversation->workspace_id
        ));
    }

    /**
     * Handle a reaction update on a message.
     *
     * @param LiveChatMessage $message
     * @param string $emoji
     * @return void
     */
    public function handleReactionUpdate(LiveChatMessage $message, Conversation $conversation, ?string $emoji)
    {
        $payload = [
            'message_id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'conversation' => [
                'data' => new \App\Http\Responses\Conversation($conversation)
            ],
            'reaction' => [
                'emoji' => $emoji
            ]
        ];

        // Dispatch the unified event to the conversation channel (for widget)
        event(new UnifiedMessageEvent(
            'livechat',
            'update-reaction',
            $payload,
            $message->channel_id,
            null,//$message->conversation_id,
            $message->conversation->workspace_id
        ));
    }

    public function handleAgentReactionUpdate(LiveChatMessage $message, ?string $emoji)
    {
        $payload = [
            'message_id' => $message->id,
            'timestamp' => now(),
            'emoji' => $emoji,
            'conversation_id' => $message->conversation_id
        ];

        // Dispatch the unified event
        event(new UnifiedMessageEvent(
            'livechat',
            'update-reaction',
            $payload,
            $message->channel_id,
            $message->conversation_id,
            $message->conversation->workspace_id
        ));
    }

    /**
     * Extract content from the LiveChat message based on its messageable type.
     *
     * @param LiveChatMessage $message
     * @return mixed
     */
    private function getMessageContent(LiveChatMessage $message)
    {
        if ($message->messageable) {
            // Handle different message types (text, image, etc.)
            return $message->messageable->content ?? $message->messageable;
        }

        return null;
    }

    public function trackAgentView(string $conversationId, string $agentId)
    {
        // Record or update the agent's last view time for this conversation
        ConversationAgent::updateOrCreate(
            [
                'conversation_id' => $conversationId,
                'inbox_agent_id' => $agentId
            ]
        );
    }

    /**
     * Handle a conversation being closed by an agent
     *
     * @param Conversation $conversation
     * @return void
     */
    public function handleConversationClosed(Conversation $conversation, string $type = null)
    {
        // Get channel information
        $channel = $conversation->channel;

        // Create payload for the event
        $payload = [
            'conversation_id' => $conversation->id,
            'status' => Conversation::STATUS_CLOSED,
            'previous_status' => $conversation->getOriginal('status'),
            'closed_by' => [
                'id' => auth()->id(),
                'name' => auth()->check() ? auth()->user()->name : 'Agent',
                'type' => 'agent'
            ],
            'closed_at' => now()->timestamp,
            'conversation' => [
                'data' => new \App\Http\Responses\Conversation($conversation)
            ]
        ];
        // Dispatch unified event for conversation closure
        event(new UnifiedMessageEvent(
            Channel::LIVECHAT_PLATFORM,
            'conversation-closed',
            $payload,
            $channel->id,
            $type == 'agent' ? $conversation->id : null,
            $conversation->workspace_id

        ));

        // Check if we should notify the contact/consumer
        // This could be done via a system message or other notification method
        $this->sendConversationEndedNotification($conversation);

        // You may also want to clear agent viewing status
        ConversationAgent::where('conversation_id', $conversation->id)->delete();
    }

    /**
     * Handle a conversation being reopened by an agent
     *
     * @param Conversation $conversation
     * @return void
     */
    public function handleConversationReopened(Conversation $conversation)
    {
        // Get channel information
        $channel = $conversation->channel;

        // Create payload for the event
        $payload = [
            'conversation_id' => $conversation->id,
            'status' => Conversation::STATUS_ACTIVE,
            'previous_status' => $conversation->getOriginal('status'),
            'reopened_by' => [
                'id' => auth()->id(),
                'name' => auth()->user()->name,
                'type' => 'agent'
            ],
            'reopened_at' => now()->timestamp,
            'conversation' => [
                'data' => new \App\Http\Responses\Conversation($conversation)
            ]
        ];

        // Dispatch unified event for conversation closure
        event(new UnifiedMessageEvent(
            Channel::LIVECHAT_PLATFORM,
            'conversation-reopened',
            $payload,
            $channel->id,
            $conversation->id,
            $conversation->workspace_id
        ));
        // Reset agent tracking for the conversation
        $this->trackAgentView($conversation->id, auth()->id());
    }

    /**
     * Send a notification to the customer that the conversation has ended
     *
     * @param Conversation $conversation
     * @return void
     */
    private function sendConversationEndedNotification(Conversation $conversation)
    {
        // Create a system message to notify the customer
        $textMessage = LiveChatTextMessage::create([
            'text' => 'Archived - closed by agent',
        ]);
        $channel = $conversation->channel;
        $connector = $channel->connector;
        $liveChatConfiguration = LiveChatConfiguration::where('connector_id', $connector->id)->firstOrFail();

        $message = $conversation->messages()->create([
            'channel_id' => $conversation->channel_id,
            'type' => 'text',
            'status' => 'sent',
            'widget_id' => $liveChatConfiguration->widget_id,
            'content' => 'Archived - closed by agent',
            'sender_type' => Widget::class,
            'sender_id' => $liveChatConfiguration->widget_id, // System message has no sender ID
            'messageable_type' => get_class($textMessage),
            'messageable_id' => $textMessage->id,
            'is_read' => false,
            'is_system_message' => true,
        ]);

        // Send the message to the customer
        $this->handleIncomingMessage($message, $conversation);
    }

    /**
     * Send a notification to the customer that the conversation has been reopened
     *
     * @param Conversation $conversation
     * @return void
     */
    private function sendConversationReopenedNotification(Conversation $conversation)
    {
        // Create a system message to notify the customer
        $textMessage = LiveChatTextMessage::create([
            'text' => 'This conversation has been reopened. An agent will assist you shortly.',
        ]);

        $message = $conversation->messages()->create([
            'channel_id' => $conversation->channel_id,
            'type' => 'text',
            'status' => 'sent',
            'content' => 'This conversation has been reopened. An agent will assist you shortly.',
            'sender_type' => 'system',
            'sender_id' => 'system',
            'messageable_type' => get_class($textMessage),
            'messageable_id' => $textMessage->id,
            'is_read' => false,
            'is_system_message' => true,
        ]);

        // Send the message to the customer
        $this->handleIncomingMessage($message, $conversation);
    }
}
