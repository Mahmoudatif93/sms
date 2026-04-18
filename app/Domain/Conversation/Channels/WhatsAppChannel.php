<?php

namespace App\Domain\Conversation\Channels;

use App\Constants\Meta;
use App\Domain\Conversation\Services\WhatsAppMessageService;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\WhatsappConsumerPhoneNumber;
use App\Models\WhatsappMessage;
use App\Models\WhatsappMessageStatus;
use App\Models\WhatsappPhoneNumber;
use App\Services\LanguageDetectionService;
use App\Traits\BusinessTokenManager;
use App\Traits\WhatsappWalletManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel extends AbstractChannel
{
    use BusinessTokenManager, WhatsappWalletManager;

    protected WhatsAppMessageService $messageService;

    public function __construct(WhatsAppMessageService $messageService)
    {
        $this->messageService = $messageService;
    }

    protected array $supportedMessageTypes = [
        'text',
        'image',
        'video',
        'audio',
        'document',
        'location',
        'template',
        'interactive',
        'flow',
        'files',
        'reaction',
    ];

    public function getPlatform(): string
    {
        return Channel::WHATSAPP_PLATFORM;
    }

    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $channel = $conversation->channel;
        $connector = $channel->connector;
        $whatsappConfiguration = $connector->whatsappConfiguration;
        if (!$whatsappConfiguration || !$whatsappConfiguration->primary_whatsapp_phone_number_id) {
            return $this->errorResponse('WhatsApp Configuration is missing or incomplete', null, 400);
        }

        $request->merge(['from' => (string) $whatsappConfiguration->primary_whatsapp_phone_number_id]);

        $contact = $conversation->contact;
        if (!$contact || !$contact->getPhoneNumberIdentifier()) {
            return $this->errorResponse('Conversation has an invalid contact.', null, 422);
        }

        $senderPhone = $contact->getPhoneNumberIdentifier();
        $request->merge([
            'to' => $senderPhone,
            'conversation_id' => $conversation->id,
        ]);
        $this->translateOutgoingMessage($request, $conversation);

        $messageType = $request->input('type');
        if ($messageType === 'template') {
            try {
                $transaction = $this->prepareWalletTransactionForTemplate(
                    channel: $channel,
                    conversation: $conversation,
                    workspace: $conversation->workspace,
                    contact: $contact,
                    senderPhone: $senderPhone,
                    templateId: $request->get('template_id')
                );
                if ($transaction) {
                    $request->merge(['transaction_id' => $transaction->id]);
                }
            } catch (\Exception $e) {
                return $this->errorResponse($e->getMessage(), null, 422);
            }
        }

        return parent::sendMessage($request, $conversation);
    }

    public function sendTextMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->messageService->sendTextMessage($request, $conversation);
    }

    public function sendFileMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->messageService->sendFilesMessage($request, $conversation);
    }

    public function sendImageMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->messageService->sendImageMessage($request, $conversation);
    }

    public function sendVideoMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->messageService->sendVideoMessage($request, $conversation);
    }

    public function sendAudioMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->messageService->sendAudioMessage($request, $conversation);
    }

    public function sendDocumentMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->messageService->sendDocumentMessage($request, $conversation);
    }

    public function sendLocationMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->messageService->sendLocationMessage($request, $conversation);
    }

    public function sendTemplateMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->messageService->sendTemplateMessage($request, $conversation);
    }

    public function sendInteractiveMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->messageService->sendInteractiveMessage($request, $conversation);
    }

    public function sendFlowMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->messageService->sendFlowMessage($request, $conversation);
    }

    public function sendReactionMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->messageService->sendReactionMessage($request, $conversation);
    }

    public function markAsRead(Conversation $conversation): JsonResponse
    {
        $contact = $conversation->contact;
        if (!$contact) {
            return $this->errorResponse('Contact information is missing for this conversation.', null, 400);
        }

        $phoneNumberId = $contact->getPhoneNumberIdentifier();
        if (!$phoneNumberId) {
            return $this->errorResponse('Valid phone number is missing for this contact.', null, 400);
        }

        $channel = $conversation->channel;
        $whatsappPhoneNumber = $channel->connector->whatsappConfiguration->whatsappPhoneNumber;

        if (!$whatsappPhoneNumber) {
            return $this->errorResponse('WhatsApp phone number configuration is missing.', null, 400);
        }

        $consumerPhoneNumber = WhatsappConsumerPhoneNumber::where('phone_number', $phoneNumberId)
            ->where('whatsapp_business_account_id', $whatsappPhoneNumber->whatsapp_business_account_id)
            ->first();

        if (!$consumerPhoneNumber) {
            return $this->errorResponse('WhatsApp consumer phone number not found.', null, 404);
        }

        $messages = WhatsappMessage::where(function ($query) use ($consumerPhoneNumber, $whatsappPhoneNumber) {
            $query->where('recipient_type', WhatsappPhoneNumber::class)
                ->where('recipient_id', $whatsappPhoneNumber->id)
                ->where('sender_id', $consumerPhoneNumber->id);
        })
            ->where('direction', WhatsappMessage::MESSAGE_DIRECTION_RECEIVED)
            ->where('status', WhatsappMessage::MESSAGE_STATUS_DELIVERED)
            ->get();

        $count = 0;
        foreach ($messages as $message) {
            $this->markMessageAsReadApi($whatsappPhoneNumber, $message->id);
            $message->status = WhatsappMessage::MESSAGE_STATUS_READ;
            $message->save();
            $this->saveMessageStatus($message->id, WhatsappMessage::MESSAGE_STATUS_READ);
            $count++;
        }

        return $this->successResponse(
            $count > 0 ? "{$count} WhatsApp messages marked as read." : "No unread WhatsApp messages found.",
            ['marked_count' => $count]
        );
    }

    public function markAsDelivered(Conversation $conversation): JsonResponse
    {
        return $this->errorResponse('Mark as delivered is handled automatically by WhatsApp', null, 400);
    }

    private function markMessageAsReadApi(WhatsappPhoneNumber $whatsappPhoneNumber, string $messageId): void
    {
        $whatsappBusinessAccount = $whatsappPhoneNumber->whatsappBusinessAccount;

        if ($whatsappBusinessAccount->name == 'Dreams SMS') {
            $accessToken = Meta::ACCESS_TOKEN;
        } else {
            $accessToken = $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);
        }

        if (!$accessToken) {
            Log::error('Failed to get a valid access token for marking WhatsApp message as read');
            return;
        }

        $response = Http::withToken($accessToken)
            ->post("https://graph.facebook.com/v20.0/{$whatsappPhoneNumber->id}/messages", [
                'messaging_product' => 'whatsapp',
                'status' => 'read',
                'message_id' => $messageId,
            ]);

        if (!$response->successful()) {
            Log::error('Failed to mark WhatsApp message as read via API', [
                'message_id' => $messageId,
                'status_code' => $response->status(),
                'response' => $response->json()
            ]);
        }
    }

    private function saveMessageStatus(string $messageId, string $status): void
    {
        WhatsappMessageStatus::create([
            'whatsapp_message_id' => $messageId,
            'status' => $status,
            'timestamp' => now()->timestamp,
        ]);
    }

    /**
     * Override to add wallet transaction for translation
     */
    protected function translateOutgoingMessage(Request $request, Conversation $conversation): void
    {
        try {
            $messageType = $request->input('type');
            if ($messageType !== 'text') {
                return;
            }

            $targetLanguage = $conversation->detected_language;
            if (!$targetLanguage) {
                return;
            }

            $textBody = $request->input('text.body');
            if (!$textBody) {
                return;
            }

            $organization = $conversation->workspace->organization ?? null;
            if (!$organization || !$organization->isAutoTranslationEnabled()) {
                return;
            }

            $languageDetectionService = app(LanguageDetectionService::class);
            $sourceLanguage = $languageDetectionService->detect($textBody);
            if ($sourceLanguage && $sourceLanguage === $targetLanguage) {
                return;
            }

            // WhatsApp-specific: prepare wallet transaction
            $transaction = $this->prepareWalletTransactionForTranslation($conversation->workspace);

            $translatedText = $this->translateText($conversation->workspace, $textBody, $targetLanguage);
            if ($translatedText && $translatedText !== $textBody) {
                $request->merge([
                    'text' => [
                        'body' => $translatedText,
                        'preview_url' => $request->input('text.preview_url'),
                    ],
                    'original_text' => $textBody,
                    'translated_to' => $targetLanguage,
                    'translation_transaction_id' => $transaction?->id,
                ]);
            } else {
                if ($transaction) {
                    $this->releaseFunds($transaction);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to translate outgoing message', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
