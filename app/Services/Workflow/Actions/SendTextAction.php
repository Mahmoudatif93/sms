<?php

namespace App\Services\Workflow\Actions;

use App\Constants\Meta;
use App\Enums\Workflow\ActionType;
use App\Models\WhatsappConsumerPhoneNumber;
use App\Models\WhatsappMessage;
use App\Models\WhatsappPhoneNumber;
use App\Models\WhatsappTextMessage;
use App\Models\WhatsappWorkflowAction;
use App\Services\ChatbotQuotaService;
use App\Traits\BusinessTokenManager;
use App\Traits\WhatsappMessageManager;
use App\Traits\WhatsappPhoneNumberManager;
use Exception;
use Illuminate\Support\Facades\Http;

/**
 * Action to send a WhatsApp text message as part of a workflow.
 */
class SendTextAction extends BaseAction
{
    use BusinessTokenManager, WhatsappMessageManager, WhatsappPhoneNumberManager;

    /**
     * Get the action type enum.
     */
    public static function getType(): ActionType
    {
        return ActionType::SEND_TEXT;
    }

    /**
     * Validate the action configuration.
     */
    public function validateConfig(array $config): bool
    {
        return isset($config['text']) && !empty($config['text']);
    }

    /**
     * Execute the action to send a text message.
     */
    public function execute(WhatsappWorkflowAction $action, WhatsappMessage $triggerMessage): array
    {
        $config = $action->action_config;

        $text = $config['text'] ?? null;
        $previewUrl = $config['preview_url'] ?? false;

        if (empty($text)) {
            throw new Exception('Text is required for SendTextAction');
        }

        // Get the WhatsApp phone number to send from
        $whatsappPhoneNumber = $triggerMessage->whatsappPhoneNumber;
        if (!$whatsappPhoneNumber) {
            throw new Exception('Could not find WhatsApp phone number for message');
        }

        // Get access token
        $accessToken = $this->getAccessToken($whatsappPhoneNumber);

        return $this->sendAndSaveTextMessage(
            $triggerMessage,
            $whatsappPhoneNumber,
            $text,
            $previewUrl,
            $accessToken
        );
    }

    /**
     * Send text message and save to database with wallet transaction.
     */
    protected function sendAndSaveTextMessage(
        WhatsappMessage $triggerMessage,
        WhatsappPhoneNumber $whatsappPhoneNumber,
        string $text,
        bool $previewUrl,
        string $accessToken
    ): array {
        // Get the recipient phone number from the original message
        $recipientPhoneNumber = $this->getRecipientPhoneNumber($triggerMessage);
        if (!$recipientPhoneNumber) {
            throw new Exception('Could not determine recipient phone number');
        }

        // Deduct chatbot quota before sending the message
        $workspace = $triggerMessage->workspace ?? $triggerMessage->conversation->workspace;
        $quotaService = new ChatbotQuotaService();
        $transaction = $quotaService->prepareWalletTransactionForChatbootAi($workspace);
        if (!$transaction) {
            return [];
        }
        // Send the text message
        $response = $this->sendTextMessage(
            $whatsappPhoneNumber,
            $recipientPhoneNumber,
            $text,
            $previewUrl,
            $accessToken
        );

        if (!$response['success']) {
            throw new Exception('Failed to send text message: ' . ($response['error'] ?? 'Unknown error'));
        }

        $responseData = $response['data'];
        $messageId = $responseData['messages'][0]['id'] ?? null;

        // Create message record
        $whatsappMessage = $this->createMessageRecord(
            $whatsappPhoneNumber,
            $recipientPhoneNumber,
            $text,
            $previewUrl,
            $messageId,
            $triggerMessage
        );

        if ($whatsappMessage) {
            $meta = [
                'type' => 'whatsapp_message',
                'whatsapp_message_id' => $whatsappMessage->id
            ];
            $transaction->meta = $meta;
            $transaction->save();
            $quotaService->finalizeWhatsappWalletTransactionChatboot($whatsappMessage, $whatsappMessage->status);
        }

        $this->log('Text message sent via workflow', [
            'message_id' => $messageId,
            'whatsapp_message_id' => $whatsappMessage->id,
            'recipient' => $recipientPhoneNumber,
        ]);

        return $this->success('Text message sent successfully', [
            'message_id' => $messageId,
            'whatsapp_message_id' => $whatsappMessage->id,
            'recipient' => $recipientPhoneNumber,
        ]);
    }

    /**
     * Get the recipient phone number from the trigger message.
     */
    protected function getRecipientPhoneNumber(WhatsappMessage $message): ?string
    {
        if ($message->sender_role === WhatsappMessage::MESSAGE_SENDER_ROLE_BUSINESS) {
            return $message->recipient?->phone_number ?? $message->recipient?->wa_id;
        }
        return $message->sender?->phone_number ?? $message->sender?->wa_id;
    }

    /**
     * Get the access token for the WhatsApp phone number.
     */
    protected function getAccessToken(WhatsappPhoneNumber $whatsappPhoneNumber): string
    {
        $businessAccount = $whatsappPhoneNumber->whatsappBusinessAccount;

        if ($businessAccount->name === 'Dreams SMS') {
            return Meta::ACCESS_TOKEN;
        }

        $token = $this->getValidAccessToken($businessAccount->business_manager_account_id);

        if (!$token) {
            throw new Exception('Failed to get valid access token');
        }

        return $token;
    }

    /**
     * Send a text message via WhatsApp API.
     */
    protected function sendTextMessage(
        WhatsappPhoneNumber $whatsappPhoneNumber,
        string $recipientPhoneNumber,
        string $text,
        bool $previewUrl,
        string $accessToken
    ): array {
        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $version = env('FACEBOOK_GRAPH_API_VERSION', 'v23.0');
        $url = "$baseUrl/$version/{$whatsappPhoneNumber->id}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $recipientPhoneNumber,
            'type' => 'text',
            'text' => [
                'preview_url' => $previewUrl,
                'body' => $text,
            ]
        ];

        $response = Http::withToken($accessToken)->post($url, $payload);

        if (!$response->successful()) {
            return [
                'success' => false,
                'error' => $response->json()['error']['message'] ?? 'API request failed',
            ];
        }

        return [
            'success' => true,
            'data' => $response->json(),
        ];
    }

    /**
     * Create a WhatsappMessage record for the sent text message.
     */
    protected function createMessageRecord(
        WhatsappPhoneNumber $whatsappPhoneNumber,
        string $recipientPhoneNumber,
        string $text,
        bool $previewUrl,
        ?string $messageId,
        WhatsappMessage $triggerMessage
    ): WhatsappMessage {
        // Find or create consumer phone number
        $consumerPhone = WhatsappConsumerPhoneNumber::firstOrCreate(
            ['phone_number' => $recipientPhoneNumber],
            ['phone_number' => $recipientPhoneNumber]
        );

        // Create the WhatsApp message
        $whatsappMessage = WhatsappMessage::create([
            'id' => $messageId,
            'whatsapp_phone_number_id' => $whatsappPhoneNumber->id,
            'sender_type' => WhatsappPhoneNumber::class,
            'sender_id' => $whatsappPhoneNumber->id,
            'recipient_type' => WhatsappConsumerPhoneNumber::class,
            'recipient_id' => $consumerPhone->id,
            'type' => WhatsappMessage::MESSAGE_TYPE_TEXT,
            'direction' => WhatsappMessage::MESSAGE_DIRECTION_SENT,
            'status' => WhatsappMessage::MESSAGE_STATUS_SENT,
            'conversation_id' => $triggerMessage->conversation_id,
            'from' => $whatsappPhoneNumber->display_phone_number,
            'to' => $recipientPhoneNumber,
        ]);

        // Create the text message record
        $textMessage = WhatsappTextMessage::create([
            'whatsapp_message_id' => $whatsappMessage->id,
            'body' => $text,
            'preview_url' => $previewUrl,
        ]);

        // Update messageable relation
        $whatsappMessage->update([
            'messageable_id' => $textMessage->id,
            'messageable_type' => WhatsappTextMessage::class,
        ]);

        // Save initial status
        $this->saveMessageStatus(
            (string) $whatsappMessage->id,
            WhatsappMessage::MESSAGE_STATUS_INITIATED
        );

        return $whatsappMessage;
    }
}
