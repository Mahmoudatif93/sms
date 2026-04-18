<?php

namespace App\Services\Workflow\Actions;

use App\Constants\Meta;
use App\Enums\Workflow\ActionType;
use App\Models\WhatsappBusinessAccount;
use App\Models\WhatsappMessage;
use App\Models\WhatsappMessageTemplate;
use App\Models\WhatsappWorkflowAction;
use App\Services\ChatbotQuotaService;
use App\Traits\BusinessTokenManager;
use App\Traits\WhatsappMessageManager;
use App\Traits\WhatsappTemplateManager;
use App\Traits\WhatsappWalletManager;
use Exception;

/**
 * Action to send a WhatsApp template message as part of a workflow.
 */
class SendTemplateAction extends BaseAction
{
    use BusinessTokenManager, WhatsappMessageManager, WhatsappTemplateManager, WhatsappWalletManager;

    /**
     * Get the action type enum.
     */
    public static function getType(): ActionType
    {
        return ActionType::SEND_TEMPLATE;
    }

    /**
     * Validate the action configuration.
     */
    public function validateConfig(array $config): bool
    {
        return isset($config['template_id']) && !empty($config['template_id']);
    }

    /**
     * Execute the action to send a template message.
     */
    public function execute(WhatsappWorkflowAction $action, WhatsappMessage $triggerMessage): array
    {
        $templateId = $this->getConfig($action, 'template_id');
        $components = $this->getConfig($action, 'components', []);

        if (!$templateId) {
            throw new Exception('Template ID is required for SendTemplateAction');
        }

        // Get template details
        $template = WhatsappMessageTemplate::find($templateId);
        if (!$template) {
            throw new Exception("Template with ID {$templateId} not found");
        }

        // Get access token
        $accessToken = $this->getAccessToken($template);
        if (!$accessToken) {
            throw new Exception('Could not obtain access token');
        }

        // Fetch template from API
        $templateData = $this->fetchTemplateFromAPI($templateId, $accessToken);
        if (!$templateData['success']) {
            throw new Exception('Failed to fetch template: ' . ($templateData['error'] ?? 'Unknown error'));
        }

        // Build components if template has variables
        $toSendComponents = [];
        if ($this->templateHasVariables($templateData['template'])) {
            if (!empty($components)) {
                $validatedComponents = $this->validateAndBuildComponents($templateData['template'], $components);
                if (!$validatedComponents['success']) {
                    throw new Exception('Failed to validate components: ' . ($validatedComponents['error'] ?? 'Unknown error'));
                }
                $toSendComponents = $validatedComponents['components'] ?? [];
            }
        }

        return $this->sendAndSaveTemplateMessage(
            $triggerMessage,
            $template,
            $templateData['template'],
            $toSendComponents,
            $accessToken
        );
    }

    /**
     * Send template message and save to database with wallet transaction.
     */
    protected function sendAndSaveTemplateMessage(
        WhatsappMessage $triggerMessage,
        WhatsappMessageTemplate $template,
        array $templateData,
        array $toSendComponents,
        string $accessToken
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

        $workspace = $triggerMessage->workspace ?? $triggerMessage->conversation->workspace;
        // Deduct chatbot quota before sending the message
        $quotaService = new ChatbotQuotaService();
        $transaction = $quotaService->prepareWalletTransactionForChatbootAi($workspace);
        if (!$transaction) {
             return [];
        }
        // Send the template message
        $response = $this->sendWhatsAppTemplateMessage(
            collect([
                'language' => ['code' => $template->language],
                'to' => $recipientPhoneNumber,
                'from' => $fromPhoneNumberId,
            ]),
            $accessToken,
            $toSendComponents,
            $template->name
        );

        if (!$response['success']) {
            throw new Exception('Failed to send template: ' . ($response['error'] ?? 'Unknown error'));
        }

        // Save the message
        $whatsappMessage = $this->saveTemplateMessageAndComponents(
            collect([
                'language' => ['code' => $template->language],
                'to' => $recipientPhoneNumber,
                'from' => $fromPhoneNumberId,
                'conversation_id' => $triggerMessage->conversation_id,
            ]),
            $response['data'],
            $toSendComponents,
            $templateData
        );

        $whatsappMessage->status = WhatsappMessage::MESSAGE_STATUS_SENT;
        // Update wallet transaction meta
        $meta = [
            'type' => 'whatsapp_message_interactive',
            'whatsapp_message_id' => $whatsappMessage->id
        ];
        $transaction->meta = $meta;
        $transaction->save();
        $quotaService->finalizeWhatsappWalletTransactionChatboot($whatsappMessage, $whatsappMessage->status);


        $this->log('Template message sent via workflow', [
            'template_id' => $template->id,
            'recipient' => $recipientPhoneNumber,
            'message_id' => $whatsappMessage->id ?? null,
        ]);

        return $this->success('Template message sent successfully', [
            'message_id' => $whatsappMessage->id ?? null,
            'template_id' => $template->id,
        ]);
    }

    /**
     * Get the recipient phone number from the trigger message.
     */
    protected function getRecipientPhoneNumber(WhatsappMessage $message): ?string
    {
        // If the message was sent by business, recipient is the consumer
        if ($message->sender_role === WhatsappMessage::MESSAGE_SENDER_ROLE_BUSINESS) {
            return $message->recipient?->phone_number ?? $message->recipient?->wa_id;
        }

        // If the message was received from consumer, sender is the consumer
        return $message->sender?->phone_number ?? $message->sender?->wa_id;
    }

    /**
     * Get the access token for the template's business account.
     */
    protected function getAccessToken(WhatsappMessageTemplate $template): ?string
    {
        $whatsappBusinessAccount = WhatsappBusinessAccount::find($template->whatsapp_business_account_id);
        if (!$whatsappBusinessAccount) {
            return null;
        }

        if ($whatsappBusinessAccount->name === 'Dreams SMS') {
            return Meta::ACCESS_TOKEN;
        }

        return $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);
    }
}
