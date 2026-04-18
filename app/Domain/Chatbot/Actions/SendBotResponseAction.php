<?php

namespace App\Domain\Chatbot\Actions;

use App\Domain\Chatbot\DTOs\ChatbotResponseDTO;
use App\Domain\Conversation\Services\ChannelResolver;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\WhatsappMessage;
use App\Services\ChatbotQuotaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SendBotResponseAction
{
    public function __construct(
        private ChannelResolver $channelResolver,
        private ChatbotQuotaService $quotaService,
    ) {}

    public function execute(Conversation $conversation, ChatbotResponseDTO $response): void
    {
        if (!$response->message) {
            return;
        }
        $this->sendTextMessage($conversation, $response->message);
    }

    public function sendTextMessage(Conversation $conversation, string $message): void
    {
        try {
            $channel = $this->channelResolver->resolve($conversation->platform);

            if (!$channel) {
                Log::warning('Chatbot: Could not resolve channel', [
                    'conversation_id' => $conversation->id,
                    'platform' => $conversation->platform,
                ]);
                return;
            }

            // For WhatsApp: Prepare wallet transaction before sending
            $transaction = null;
            if ($conversation->platform === Channel::WHATSAPP_PLATFORM) {
                $workspace = $conversation->channel?->workspaces()?->first();
                if ($workspace) {
                    $transaction = $this->quotaService->prepareWalletTransactionForChatbootAi($workspace);
                    if (!$transaction) {
                        Log::warning('Chatbot: Insufficient balance for chatbot message', [
                            'conversation_id' => $conversation->id,
                        ]);
                        return;
                    }
                }
            }

            // Create a request with the message data
            $request = $this->createMessageRequest($conversation, $message);

            // Get the last message ID before sending (to find the new one after)
            $lastMessageId = null;
            if ($conversation->platform === Channel::WHATSAPP_PLATFORM) {
                $lastMessageId = WhatsappMessage::where('conversation_id', $conversation->id)
                    ->where('sender_role', WhatsappMessage::MESSAGE_SENDER_ROLE_BUSINESS)
                    ->latest()
                    ->value('id');
            }

            // Send via the appropriate channel
            $result = $channel->sendTextMessage($request, $conversation);

            // For WhatsApp: Finalize wallet transaction after sending
            if ($transaction && $conversation->platform === Channel::WHATSAPP_PLATFORM) {
                // Find the newly created message (created after the last one)
                $query = WhatsappMessage::where('conversation_id', $conversation->id)
                    ->where('sender_role', WhatsappMessage::MESSAGE_SENDER_ROLE_BUSINESS)
                    ->latest();

                if ($lastMessageId) {
                    $query->where('id', '!=', $lastMessageId);
                }

                $whatsappMessage = $query->first();

                if ($whatsappMessage) {
                    $transaction->meta = [
                        'type' => 'chatbot_message',
                        'whatsapp_message_id' => $whatsappMessage->id,
                        'conversation_id' => $conversation->id,
                    ];
                    $transaction->save();

                    $this->quotaService->finalizeWhatsappWalletTransactionChatboot(
                        $whatsappMessage,
                        $whatsappMessage->status ?? WhatsappMessage::MESSAGE_STATUS_SENT
                    );
                }
            }

        } catch (\Exception $e) {
            Log::error('Chatbot: Failed to send response', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function createMessageRequest(Conversation $conversation, string $message): Request
    {
        $baseData = [
            'type' => 'text',
            'conversation_id' => $conversation->id,
        ];

        $platformData = match ($conversation->platform) {
            Channel::WHATSAPP_PLATFORM => $this->buildWhatsAppData($conversation, $message),
            Channel::LIVECHAT_PLATFORM => $this->buildLiveChatData($conversation, $message),
            Channel::MESSENGER_PLATFORM => $this->buildMessengerData($conversation, $message),
            default => ['message' => $message],
        };

        return new Request(array_merge($baseData, $platformData));
    }

    private function buildWhatsAppData(Conversation $conversation, string $message): array
    {
        // Get the WhatsApp phone number ID (from)
        $fromPhoneNumberId = $this->getWhatsAppFromPhoneNumberId($conversation);

        // Get the customer's phone number (to)
        $toPhoneNumber = $this->getWhatsAppToPhoneNumber($conversation);

        return [
            'from' => $fromPhoneNumberId,
            'to' => $toPhoneNumber,
            'text' => [
                'body' => $message,
                'preview_url' => false,
            ],
        ];
    }

    private function buildLiveChatData(Conversation $conversation, string $message): array
    {
        return [
            'message' => $message,
            'session_id' => $conversation->id,
        ];
    }

    private function buildMessengerData(Conversation $conversation, string $message): array
    {
        // Get the recipient PSID from MessengerConsumer
        $recipientPsid = $this->getMessengerRecipientPsid($conversation);

        return [
            'message' => $message,
            'recipient_id' => $recipientPsid,
        ];
    }

    private function getMessengerRecipientPsid(Conversation $conversation): ?string
    {
        $contact = $conversation->contact;

        if (!$contact) {
            return null;
        }

        // Get the PSID from the messenger consumer relation
        $messengerConsumer = $contact->messengerConsumers()->first();

        return $messengerConsumer?->psid;
    }

    private function getWhatsAppFromPhoneNumberId(Conversation $conversation): ?string
    {
        $channel = $conversation->channel;

        if (!$channel) {
            return null;
        }

        $connector = $channel->connector;
        $whatsappConfig = $connector?->whatsappConfiguration;

        return $whatsappConfig?->primary_whatsapp_phone_number_id;
    }

    private function getWhatsAppToPhoneNumber(Conversation $conversation): ?string
    {
        $contact = $conversation->contact;

        if (!$contact) {
            return null;
        }

        return $contact->getPhoneNumberIdentifier();
    }
}
