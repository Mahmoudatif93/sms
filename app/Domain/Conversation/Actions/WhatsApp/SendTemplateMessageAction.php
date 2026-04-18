<?php

namespace App\Domain\Conversation\Actions\WhatsApp;

use App\Constants\Meta;
use App\Domain\Conversation\DTOs\WhatsAppMessageResultDTO;
use App\Domain\Conversation\Repositories\WhatsAppMessageRepositoryInterface;
use App\Models\TemplateBodyCurrencyParameter;
use App\Models\TemplateBodyDateTimeParameter;
use App\Models\TemplateBodyTextParameter;
use App\Models\TemplateHeaderImageParameter;
use App\Models\TemplateMessageBodyComponent;
use App\Models\TemplateMessageHeaderComponent;
use App\Models\WhatsappMessage;
use App\Models\WhatsappPhoneNumber;
use App\Models\WhatsappTemplateMessage;
use App\Traits\BusinessTokenManager;
use App\Traits\WhatsappPhoneNumberManager;
use App\Traits\WhatsappTemplateManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendTemplateMessageAction
{
    use BusinessTokenManager, WhatsappPhoneNumberManager, WhatsappTemplateManager;

    public function __construct(
        private WhatsAppMessageRepositoryInterface $repository
    ) {
    }

    public function execute(Request $request): WhatsAppMessageResultDTO
    {
        try {
            // Validate request
            $validationResult = $this->validateTemplateMessageRequest($request);
            if (!$validationResult['success']) {
                return WhatsAppMessageResultDTO::failure(
                    'Validation Error(s)',
                    422
                );
            }

            $fromPhoneNumberId = $request->input('from');

            // Get access token
            $accessToken = $this->getAccessToken($fromPhoneNumberId);
            if (!$accessToken) {
                return WhatsAppMessageResultDTO::failure('Failed to get a valid access token', 401);
            }

            // Fetch template from API
            $template = $this->fetchTemplateFromAPI($request->get('template_id'), $accessToken);
            if (!$template['success']) {
                return WhatsAppMessageResultDTO::failure($template['error'], $template['status']);
            }

            // Build components if template has variables
            $toSendComponents = [];
            if ($this->templateHasVariables($template['template'])) {
                $toSendComponents = $this->validateAndBuildComponents(
                    $template['template'],
                    $request->get('components') ?? []
                );
                if (!$toSendComponents['success']) {
                    return WhatsAppMessageResultDTO::failure($toSendComponents['error'], 400);
                }
            }

            // Send template message via WhatsApp API
            $response = $this->sendWhatsAppTemplateMessage(
                $request,
                $accessToken,
                $toSendComponents['components'] ?? null,
                $template['template']['name']
            );

            if (!$response['success']) {
                return WhatsAppMessageResultDTO::failure($response['error'], $response['status']);
            }

            // Save message and components
            $whatsappMessageWithRelations = $this->saveTemplateMessageAndComponents(
                $request,
                $response['data'],
                $toSendComponents['components'] ?? null,
                $template['template']
            );

            $whatsappMessage = WhatsappMessage::whereId($whatsappMessageWithRelations['id'])->first();

            // Update wallet transaction meta if applicable
            if ($request->has('transaction_id')) {
                $whatsappMessage->updateWalletTransactionMeta($request->get('transaction_id'));
            }

            return WhatsAppMessageResultDTO::success($whatsappMessage);

        } catch (\Exception $e) {
            Log::error('SendTemplateMessageAction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return WhatsAppMessageResultDTO::failure('An error occurred: ' . $e->getMessage(), 500);
        }
    }

    private function getAccessToken(string $phoneNumberId): ?string
    {
        $whatsappPhoneNumber = WhatsappPhoneNumber::find($phoneNumberId);
        if (!$whatsappPhoneNumber) {
            return null;
        }

        $whatsappBusinessAccount = $whatsappPhoneNumber->whatsappBusinessAccount;

        if ($whatsappBusinessAccount->name == 'Dreams SMS') {
            return Meta::ACCESS_TOKEN;
        }

        return $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);
    }

    private function sendWhatsAppTemplateMessage(Request $request, string $accessToken, ?array $components, string $templateName): array
    {
        $fromPhoneNumberId = $request->input('from');
        $toPhoneNumber = $request->input('to');
        $language = $request->input('language', 'en');

        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $version = env('FACEBOOK_GRAPH_API_VERSION', 'v20.0');
        $url = "{$baseUrl}/{$version}/{$fromPhoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toPhoneNumber,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => $request->get('language')
            ],
        ];

        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        $response = Http::withToken($accessToken)->post($url, $payload);

        if (!$response->successful()) {
            $error = $response->json()['error']['message'] ?? 'Unknown error';
            return ['success' => false, 'error' => $error, 'status' => $response->status()];
        }

        return ['success' => true, 'data' => $response->json(), 'status' => $response->status()];
    }

    private function saveTemplateMessageAndComponents(Request $request, array $responseData, ?array $components, array $template): array
    {
        $messageId = $responseData['messages'][0]['id'];
        $waId = $responseData['contacts'][0]['wa_id'];
        $fromPhoneNumberId = $request->input('from');
        $toPhoneNumber = $request->input('to');

        $whatsappPhoneNumber = WhatsappPhoneNumber::find($fromPhoneNumberId);
        $businessAccountId = $whatsappPhoneNumber->whatsapp_business_account_id;

        // Get or create recipient
        $recipient = $this->repository->findOrCreateConsumer(
            $this->normalizePhoneNumber($toPhoneNumber),
            $businessAccountId,
            $waId
        );

        // Create main message
        $message = $this->repository->create([
            'id' => $messageId,
            'whatsapp_phone_number_id' => $fromPhoneNumberId,
            'sender_type' => WhatsappPhoneNumber::class,
            'sender_id' => $fromPhoneNumberId,
            'agent_id' => auth('api')->id(),
            'recipient_id' => $recipient->id,
            'recipient_type' => get_class($recipient),
            'sender_role' => WhatsappMessage::MESSAGE_SENDER_ROLE_BUSINESS,
            'type' => WhatsappMessage::MESSAGE_TYPE_TEMPLATE,
            'direction' => WhatsappMessage::MESSAGE_DIRECTION_SENT,
            'status' => WhatsappMessage::MESSAGE_STATUS_INITIATED,
            'conversation_id' => $request->input('conversation_id'),
        ]);

        // Save template message content
        $templateMessage = WhatsappTemplateMessage::create([
            'whatsapp_message_id' => $messageId,
            'whatsapp_template_id' => $template['id'],
            'template_name' => $template['name'],
            'template_language_code' => $template['language']
        ]);

        // Update messageable relation
        $this->repository->update($message, [
            'messageable_id' => $templateMessage->id,
            'messageable_type' => WhatsappTemplateMessage::class,
        ]);

        // Save template components and parameters if template has variables
        if ($this->templateHasVariables($template) && !empty($components)) {
            $this->saveTemplateComponents($templateMessage->id, $components);
        }

        // Save initial status
        $this->repository->saveMessageStatus($messageId, WhatsappMessage::MESSAGE_STATUS_INITIATED);

        return ['id' => $messageId];
    }

    private function saveTemplateComponents(int $templateMessageId, array $components): void
    {
        foreach ($components as $component) {
            if ($component['type'] === 'body') {
                $bodyComponent = TemplateMessageBodyComponent::create([
                    'template_message_id' => $templateMessageId,
                    'type' => $component['type'],
                ]);

                foreach ($component['parameters'] ?? [] as $parameter) {
                    $this->saveBodyParameter($bodyComponent->id, $parameter);
                }
            } elseif ($component['type'] === 'header') {
                $headerComponent = TemplateMessageHeaderComponent::create([
                    'template_message_id' => $templateMessageId,
                    'type' => $component['type'],
                ]);

                foreach ($component['parameters'] ?? [] as $parameter) {
                    $this->saveHeaderParameter($headerComponent->id, $parameter);
                }
            }
        }
    }

    private function saveBodyParameter(int $bodyComponentId, array $parameter): void
    {
        switch ($parameter['type']) {
            case 'text':
                TemplateBodyTextParameter::create([
                    'template_message_body_component_id' => $bodyComponentId,
                    'text' => $parameter['text'],
                ]);
                break;

            case 'currency':
                TemplateBodyCurrencyParameter::create([
                    'template_message_body_component_id' => $bodyComponentId,
                    'fallback_value' => $parameter['currency']['fallback_value'],
                    'code' => $parameter['currency']['code'],
                    'amount_1000' => $parameter['currency']['amount_1000'],
                ]);
                break;

            case 'date_time':
                TemplateBodyDateTimeParameter::create([
                    'template_message_body_component_id' => $bodyComponentId,
                    'fallback_value' => $parameter['date_time']['fallback_value'] ?? null,
                    'day_of_week' => $this->normalizeDayOfWeek($parameter['date_time']['day_of_week'] ?? null),
                    'year' => $parameter['date_time']['year'] ?? null,
                    'month' => $parameter['date_time']['month'] ?? null,
                    'day_of_month' => $parameter['date_time']['day_of_month'] ?? null,
                    'hour' => $parameter['date_time']['hour'] ?? null,
                    'minute' => $parameter['date_time']['minute'] ?? null,
                    'calendar' => $parameter['date_time']['calendar'] ?? 'GREGORIAN',
                ]);
                break;
        }
    }

    private function saveHeaderParameter(int $headerComponentId, array $parameter): void
    {
        if ($parameter['type'] === 'image') {
            TemplateHeaderImageParameter::create([
                'tmpl_msg_hdr_component_id' => $headerComponentId,
                'link' => $parameter['image']['link'],
            ]);
        }
    }

    private function normalizeDayOfWeek($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $n = (int) $value;
            return ($n >= 1 && $n <= 7) ? $n : null;
        }

        $map = [
            'MONDAY' => 1,
            'TUESDAY' => 2,
            'WEDNESDAY' => 3,
            'THURSDAY' => 4,
            'FRIDAY' => 5,
            'SATURDAY' => 6,
            'SUNDAY' => 7,
        ];

        $key = strtoupper(trim($value));
        return $map[$key] ?? null;
    }
}
