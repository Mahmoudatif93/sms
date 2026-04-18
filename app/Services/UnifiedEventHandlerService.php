<?php

namespace App\Services;

use App\Events\LiveChatAgentMessage;
use App\Events\LiveChatIncomingMessage;
use App\Events\LiveChatStatusUpdated;
use App\Events\MessengerIncomingMessage;
use App\Events\UnifiedMessageEvent;
use App\Events\WhatsappIncomingMessage;
use App\Events\WhatsappMessageStatusUpdated;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\LiveChatTextMessage;
use App\Models\LiveChatFileMessage;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Log;

class UnifiedEventHandlerService
{
    /**
     * Process an incoming WhatsApp message event and also dispatch a unified event
     */
    public function processWhatsappIncomingMessage(WhatsappIncomingMessage $event): void
    {
        try {
            $message = $event->whatsappMessage;
            
            // Find the channel associated with this phone number
            $channel = Channel::where('platform', Channel::WHATSAPP_PLATFORM)
                ->whereHas('connector.whatsappConfiguration', function($query) use ($message) {
                    $query->where('primary_whatsapp_phone_number_id', $message->whatsapp_phone_number_id);
                })
                ->first();
                
            if (!$channel) {
                Log::warning("No channel found for WhatsApp phone number ID: {$message->whatsapp_phone_number_id}");
                return;
            }
            
            // Create payload for unified event
            $payload = [
                'message_id' => $message->id,
                'sender_id' => $message->sender_id,
                'sender_type' => $message->sender_type,
                'type' => $message->type,
                'timestamp' => $message->created_at,
                'status' => $message->status,
                'direction' => $message->direction,
            ];
            
            // Add specific content based on message type
            if ($message->type === WhatsappMessage::MESSAGE_TYPE_TEXT && $message->messageable) {
                $payload['content'] = [
                    'text' => $message->messageable->body ?? 'No content',
                ];
            } elseif ($message->type === WhatsappMessage::MESSAGE_TYPE_IMAGE && $message->imageMessage) {
                $payload['content'] = [
                    'file_url' => $message->imageMessage->getMediaUrl() ?? null,
                    'caption' => $message->imageMessage->caption ?? null,
                ];
            } elseif ($message->type === WhatsappMessage::MESSAGE_TYPE_VIDEO && $message->videoMessage) {
                $payload['content'] = [
                    'file_url' => $message->videoMessage->getSignedMediaUrlForPreview() ?? null,
                    'caption' => $message->videoMessage->caption ?? null,
                ];
            } elseif ($message->type === WhatsappMessage::MESSAGE_TYPE_AUDIO && $message->audioMessage) {
                $payload['content'] = [
                    'file_url' => $message->audioMessage->getSignedMediaUrlForPreview() ?? null,
                ];
            }
            
            // Find conversation ID if available
            $conversationId = $message->whatsapp_conversation_id;
            
            // Dispatch unified event
            event(new UnifiedMessageEvent(
                'whatsapp',
                'message',
                $payload,
                $channel->id,
                $conversationId
            ));
            
        } catch (\Exception $e) {
            Log::error("Error processing WhatsApp incoming message event: {$e->getMessage()}", [
                'exception' => $e,
                'message_id' => $event->whatsappMessage->id ?? 'unknown'
            ]);
        }
    }
    
    /**
     * Process a WhatsApp message status update event and also dispatch a unified event
     */
    public function processWhatsappStatusUpdate(WhatsappMessageStatusUpdated $event): void
    {
        try {
            $message = $event->whatsappMessage;
            
            // Find the channel associated with this phone number
            $channel = Channel::where('platform', Channel::WHATSAPP_PLATFORM)
                ->whereHas('connector.whatsappConfiguration', function($query) use ($message) {
                    $query->where('primary_whatsapp_phone_number_id', $message->whatsapp_phone_number_id);
                })
                ->first();
                
            if (!$channel) {
                Log::warning("No channel found for WhatsApp phone number ID: {$message->whatsapp_phone_number_id}");
                return;
            }
            
            // Create payload for unified event
            $payload = [
                'message_id' => $message->id,
                'status' => $message->status,
                'timestamp' => $message->updated_at,
                'errors' => $message->statuses->flatMap(function($status) {
                    return $status->errors->toArray();
                }),
            ];
            
            // Find conversation ID if available
            $conversationId = $message->whatsapp_conversation_id;
            
            // Dispatch unified event
            event(new UnifiedMessageEvent(
                'whatsapp',
                'status',
                $payload,
                $channel->id,
                $conversationId
            ));
            
        } catch (\Exception $e) {
            Log::error("Error processing WhatsApp status update event: {$e->getMessage()}", [
                'exception' => $e,
                'message_id' => $event->whatsappMessage->id ?? 'unknown'
            ]);
        }
    }
    
    /**
     * Process an incoming LiveChat message event and also dispatch a unified event
     */
    public function processLiveChatIncomingMessage(LiveChatIncomingMessage $event): void
    {
        try {
            $message = $event->liveChatMessage;
            
            // Get the channel from the message
            $channel = Channel::find($message->channel_id);
            
            if (!$channel) {
                Log::warning("No channel found for LiveChat message ID: {$message->id}");
                return;
            }
            $conversationMessage = new \App\Http\Responses\ConversationMessage($message, Channel::LIVECHAT_PLATFORM);

            // Create payload for unified event
            $payload = [
                'message_id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'sender_id' => $message->sender_id,
                'sender_type' => $message->sender_type,
                'type' => $message->type,
                'timestamp' => $message->created_at,
                'is_read' => $message->is_read,
            ];
            
            // Dispatch unified event
            event(new UnifiedMessageEvent(
                'livechat',
                'message',
                $conversationMessage->toArray(),
                $channel->id,
                $message->conversation_id
            ));
            
        } catch (\Exception $e) {
            Log::error("Error processing LiveChat incoming message event: {$e->getMessage()}", [
                'exception' => $e,
                'message_id' => $event->liveChatMessage->id ?? 'unknown'
            ]);
        }
    }
    
    /**
     * Process a LiveChat agent message event and also dispatch a unified event
     */
    public function processLiveChatAgentMessage(LiveChatAgentMessage $event): void
    {
        try {
            $message = $event->liveChatMessage;
            
            // Get the channel from the message
            $channel = Channel::find($message->channel_id);
            
            if (!$channel) {
                Log::warning("No channel found for LiveChat message ID: {$message->id}");
                return;
            }
            
            // Create payload for unified event
            $payload = [
                'message_id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'sender_id' => $message->sender_id,
                'sender_type' => $message->sender_type,
                'type' => $message->type,
                'timestamp' => $message->created_at,
                'is_read' => $message->is_read,
            ];
            
            // Add specific content based on message type
            if ($message->messageable_type === LiveChatTextMessage::class && $message->messageable) {
                $payload['content'] = [
                    'text' => $message->messageable->text ?? 'No content',
                ];
            } elseif ($message->messageable_type === LiveChatFileMessage::class && $message->messageable) {
                $payload['content'] = [
                    'file_name' => $message->messageable->file_name ?? null,
                    'file_url' => $message->messageable->file_path ? 
                        url(\Storage::url($message->messageable->file_path)) : null,
                    'mime_type' => $message->messageable->mime_type ?? null,
                ];
            }
            
            // Dispatch unified event
            event(new UnifiedMessageEvent(
                'livechat',
                'agent_message',
                $payload,
                $channel->id,
                $message->conversation_id
            ));
            
        } catch (\Exception $e) {
            Log::error("Error processing LiveChat agent message event: {$e->getMessage()}", [
                'exception' => $e,
                'message_id' => $event->liveChatMessage->id ?? 'unknown'
            ]);
        }
    }
    
    /**
     * Process a LiveChat status update event and also dispatch a unified event
     */
    public function processLiveChatStatusUpdate(LiveChatStatusUpdated $event): void
    {
        try {
            $conversation = $event->conversation;
            
            // Get the channel from the conversation
            $channel = Channel::find($conversation->channel_id);
            
            if (!$channel) {
                Log::warning("No channel found for LiveChat conversation ID: {$conversation->id}");
                return;
            }
            
            // Create payload for unified event
            $payload = [
                'conversation_id' => $conversation->id,
                'old_status' => $event->oldStatus,
                'new_status' => $event->newStatus,
                'timestamp' => $conversation->updated_at,
                'contact_id' => $conversation->contact_id,
            ];
            
            // Dispatch unified event
            event(new UnifiedMessageEvent(
                'livechat',
                'status',
                $payload,
                $channel->id,
                $conversation->id
            ));
            
        } catch (\Exception $e) {
            Log::error("Error processing LiveChat status update event: {$e->getMessage()}", [
                'exception' => $e,
                'conversation_id' => $event->conversation->id ?? 'unknown'
            ]);
        }
    }
    
}