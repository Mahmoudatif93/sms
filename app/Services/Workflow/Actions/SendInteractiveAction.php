<?php

namespace App\Services\Workflow\Actions;

use App\Constants\Meta;
use App\Enums\Workflow\ActionType;
use App\Models\InteractiveMessageDraft;
use App\Models\WhatsappConsumerPhoneNumber;
use App\Models\WhatsappInteractiveMessage;
use App\Models\WhatsappMessage;
use App\Models\WhatsappPhoneNumber;
use App\Models\WhatsappWorkflowAction;
use App\Services\ChatbotQuotaService;
use App\Traits\BusinessTokenManager;
use App\Traits\WhatsappMessageManager;
use App\Traits\WhatsappPhoneNumberManager;
use Exception;
use Illuminate\Support\Facades\Http;

/**
 * Action to send a WhatsApp interactive message as part of a workflow.
 */
class SendInteractiveAction extends BaseAction
{
    use BusinessTokenManager, WhatsappMessageManager, WhatsappPhoneNumberManager;

    /**
     * Get the action type enum.
     */
    public static function getType(): ActionType
    {
        return ActionType::SEND_INTERACTIVE;
    }

    /**
     * Validate the action configuration.
     */
    public function validateConfig(array $config): bool
    {
        // If using draft ID, just check it exists
        if (isset($config['interactive_draft_id'])) {
            return true;
        }

        // Otherwise validate inline config
        if (!isset($config['interactive_type'])) {
            return false;
        }

        $type = $config['interactive_type'];

        if ($type === 'button') {
            return isset($config['body']) && isset($config['buttons']) && is_array($config['buttons']);
        }

        if ($type === 'list') {
            return isset($config['body']) && isset($config['sections']) && is_array($config['sections']);
        }

        return false;
    }

    /**
     * Execute the action to send an interactive message.
     */
    public function execute(WhatsappWorkflowAction $action, WhatsappMessage $triggerMessage): array
    {
        $config = $action->action_config;

        // Check if using draft ID or inline config
        if (isset($config['interactive_draft_id'])) {
            return $this->executeWithDraft($config['interactive_draft_id'], $triggerMessage);
        }

        $interactiveType = $config['interactive_type'] ?? null;

        if (!$interactiveType) {
            throw new Exception('Interactive type is required for SendInteractiveAction');
        }

        // Build interactive payload
        $interactivePayload = $this->buildInteractivePayload($config, $interactiveType);

        return $this->sendAndSaveInteractiveMessage(
            $triggerMessage,
            $interactivePayload,
            $interactiveType,
            null
        );
    }

    /**
     * Execute action using an interactive message draft.
     */
    protected function executeWithDraft(int $draftId, WhatsappMessage $triggerMessage): array
    {
        $draft = InteractiveMessageDraft::find($draftId);

        if (!$draft) {
            throw new Exception("Interactive message draft not found: {$draftId}");
        }

        if (!$draft->is_active) {
            throw new Exception("Interactive message draft is inactive: {$draftId}");
        }

        // Build interactive payload from draft
        $interactivePayload = $draft->buildPayload();

        return $this->sendAndSaveInteractiveMessage(
            $triggerMessage,
            $interactivePayload,
            $draft->interactive_type,
            $draftId,
            [
                'draft_name' => $draft->name,
            ]
        );
    }

    /**
     * Send interactive message and save to database with wallet transaction.
     */
    protected function sendAndSaveInteractiveMessage(
        WhatsappMessage $triggerMessage,
        array $interactivePayload,
        string $interactiveType,
        ?int $interactiveDraftId = null,
        array $additionalLogData = []
    ): array {
        // Get the recipient phone number from the original message
        $recipientPhoneNumber = $this->getRecipientPhoneNumber($triggerMessage);
        if (!$recipientPhoneNumber) {
            throw new Exception('Could not determine recipient phone number');
        }

        // Get the WhatsApp phone number ID from the original message
        $fromPhoneNumberId = $triggerMessage->whatsapp_phone_number_id;
        if (!$fromPhoneNumberId) {
            throw new Exception('Could not determine sender phone number ID');
        }

        // Get access token
        $whatsappPhoneNumber = WhatsappPhoneNumber::find($fromPhoneNumberId);
        $accessToken = $this->getAccessToken($whatsappPhoneNumber);
        if (!$accessToken) {
            throw new Exception('Could not obtain access token');
        }

        // Prepare wallet transaction
        $workspace = $triggerMessage->workspace ?? $triggerMessage->conversation->workspace;
        $quotaService = new ChatbotQuotaService();
        $transaction = $quotaService->prepareWalletTransactionForChatbootAi($workspace);
        if(!$transaction){
             return [];
        }
        // Send the message
        $response = $this->sendInteractiveMessage(
            $fromPhoneNumberId,
            $recipientPhoneNumber,
            $interactivePayload,
            $accessToken
        );

        if (!$response['success']) {
            throw new Exception('Failed to send interactive message: ' . ($response['error'] ?? 'Unknown error'));
        }

        // Save the message
        $whatsappMessage = $this->saveInteractiveMessage(
            $triggerMessage,
            $response['data'],
            $interactiveType,
            $interactivePayload,
            $fromPhoneNumberId,
            $recipientPhoneNumber,
            $interactiveDraftId
        );
        
        // Finalize wallet transaction
        if ($whatsappMessage) {
            $meta = [
                'type' => 'whatsapp_message',
                'whatsapp_message_id' => $whatsappMessage->id
            ];
            $transaction->meta = $meta;
            $transaction->save();
            $quotaService->finalizeWhatsappWalletTransactionChatboot($whatsappMessage, $whatsappMessage->status);
        }

        // Log the action
        $logData = array_merge([
            'interactive_type' => $interactiveType,
            'recipient' => $recipientPhoneNumber,
            'message_id' => $whatsappMessage->id ?? null,
        ], $additionalLogData);

        if ($interactiveDraftId) {
            $logData['draft_id'] = $interactiveDraftId;
            $this->log('Interactive message sent via workflow using draft', $logData);
        } else {
            $this->log('Interactive message sent via workflow', $logData);
        }

        return $this->success('Interactive message sent successfully', [
            'message_id' => $whatsappMessage->id ?? null,
            'interactive_type' => $interactiveType,
            'draft_id' => $interactiveDraftId,
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
    protected function getAccessToken(WhatsappPhoneNumber $phoneNumber): ?string
    {
        $whatsappBusinessAccount = $phoneNumber->whatsappBusinessAccount;
        if (!$whatsappBusinessAccount) {
            return null;
        }

        if ($whatsappBusinessAccount->name === 'Dreams SMS') {
            return Meta::ACCESS_TOKEN;
        }

        return $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);
    }

    /**
     * Build the interactive payload based on type.
     */
    protected function buildInteractivePayload(array $config, string $type): array
    {
        $payload = [
            'type' => $type,
            'body' => ['text' => $config['body']],
        ];

        if (!empty($config['header'])) {
            $payload['header'] = $config['header'];
        }

        if (!empty($config['footer'])) {
            $payload['footer'] = ['text' => $config['footer']];
        }

        if ($type === 'button') {
            $payload['action'] = [
                'buttons' => collect($config['buttons'])->map(fn($btn) => [
                    'type' => 'reply',
                    'reply' => [
                        'id' => $btn['id'],
                        'title' => $btn['title'],
                    ],
                ])->toArray(),
            ];
        } elseif ($type === 'list') {
            $payload['action'] = [
                'button' => $config['list_button_text'] ?? 'View Options',
                'sections' => collect($config['sections'])->map(fn($section) => [
                    'title' => $section['title'],
                    'rows' => collect($section['rows'])->map(fn($row) => array_filter([
                        'id' => $row['id'],
                        'title' => $row['title'],
                        'description' => $row['description'] ?? null,
                    ]))->toArray(),
                ])->toArray(),
            ];
        }

        return $payload;
    }

    /**
     * Send the interactive message via WhatsApp API.
     */
    protected function sendInteractiveMessage(
        string $fromPhoneNumberId,
        string $toPhoneNumber,
        array $interactivePayload,
        string $accessToken
    ): array {
        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $version = env('FACEBOOK_GRAPH_API_VERSION', 'v20.0');
        $url = "$baseUrl/$version/$fromPhoneNumberId/messages";

        $message = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toPhoneNumber,
            'type' => 'interactive',
            'interactive' => $interactivePayload,
        ];

        $response = Http::withToken($accessToken)->post($url, $message);

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
     * Save the interactive message to the database.
     */
    protected function saveInteractiveMessage(
        WhatsappMessage $triggerMessage,
        array $responseData,
        string $interactiveType,
        array $interactivePayload,
        string $fromPhoneNumberId,
        string $recipientPhoneNumber,
        int $interactiveDraftId = null
    ): WhatsappMessage {
        $whatsappPhoneNumber = WhatsappPhoneNumber::find($fromPhoneNumberId);
        $whatsappBusinessAccountID = $whatsappPhoneNumber->whatsapp_business_account_id;

        $recipient = WhatsappConsumerPhoneNumber::firstOrCreate(
            [
                'phone_number' => $this->normalizePhoneNumber($recipientPhoneNumber),
                'whatsapp_business_account_id' => $whatsappBusinessAccountID,
            ],
            ['wa_id' => $responseData['contacts'][0]['wa_id'] ?? $recipientPhoneNumber]
        );

        $whatsappMessage = WhatsappMessage::create([
            'id' => $responseData['messages'][0]['id'],
            'whatsapp_phone_number_id' => $fromPhoneNumberId,
            'sender_type' => WhatsappPhoneNumber::class,
            'sender_id' => $fromPhoneNumberId,
            'recipient_id' => $recipient->id,
            'recipient_type' => WhatsappConsumerPhoneNumber::class,
            'sender_role' => WhatsappMessage::MESSAGE_SENDER_ROLE_BUSINESS,
            'type' => WhatsappMessage::MESSAGE_TYPE_INTERACTIVE,
            'direction' => WhatsappMessage::MESSAGE_DIRECTION_SENT,
            'status' => WhatsappMessage::MESSAGE_STATUS_SENT,
            'conversation_id' => $triggerMessage->conversation_id,
        ]);

        $interactiveMessage = WhatsappInteractiveMessage::create([
            'whatsapp_message_id' => $responseData['messages'][0]['id'],
            'interactive_type' => $interactiveType,
            'payload' => $interactivePayload,
            'interactive_message_draft_id' => $interactiveDraftId,
        ]);

        $whatsappMessage->update([
            'messageable_id' => $interactiveMessage->id,
            'messageable_type' => WhatsappInteractiveMessage::class,
        ]);

        $this->saveMessageStatus($whatsappMessage->id, WhatsappMessage::MESSAGE_STATUS_SENT);

        return $whatsappMessage;
    }
}
