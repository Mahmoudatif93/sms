<?php

namespace App\Domain\Conversation\Actions\WhatsApp;

use App\Constants\Meta;
use App\Domain\Conversation\DTOs\WhatsAppMessageResultDTO;
use App\Domain\Conversation\Repositories\WhatsAppMessageRepositoryInterface;
use App\Models\WhatsappFlowMessage;
use App\Models\WhatsappMessage;
use App\Models\WhatsappPhoneNumber;
use App\Rules\WhatsappValidPhoneNumber;
use App\Traits\BusinessTokenManager;
use App\Traits\WhatsappPhoneNumberManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SendFlowMessageAction
{
    use BusinessTokenManager, WhatsappPhoneNumberManager;

    public function __construct(
        private WhatsAppMessageRepositoryInterface $repository
    ) {}

    public function execute(Request $request): WhatsAppMessageResultDTO
    {
        try {
            // Validate request
            $validation = $this->validateRequest($request);
            if ($validation->fails()) {
                return WhatsAppMessageResultDTO::failure('Validation Error(s)', 422);
            }

            $fromPhoneNumberId = $request->input('from');
            $toPhoneNumber = $request->input('to');

            // Get access token
            $accessToken = $this->getAccessToken($fromPhoneNumberId);
            if (!$accessToken) {
                return WhatsAppMessageResultDTO::failure('Failed to get a valid access token', 401);
            }

            // Build flow payload
            $flowToken = Str::uuid()->toString();
            $flowPayload = $this->buildFlowPayload($request, $flowToken);

            // Send to WhatsApp API
            $response = $this->sendToApi($fromPhoneNumberId, $toPhoneNumber, $flowPayload, $accessToken);

            if (!$response->successful()) {
                return WhatsAppMessageResultDTO::failure(
                    'Failed to send message: ' . ($response->json()['error']['message'] ?? 'Unknown error'),
                    $response->status()
                );
            }

            // Save message to database
            $message = $this->saveMessage($request, $response->json(), $flowPayload, $flowToken);

            return WhatsAppMessageResultDTO::success($message);

        } catch (\Exception $e) {
            Log::error('SendFlowMessageAction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return WhatsAppMessageResultDTO::failure('An error occurred: ' . $e->getMessage(), 500);
        }
    }

    private function validateRequest(Request $request): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            'from' => ['required', 'string', 'exists:whatsapp_phone_numbers,id'],
            'to' => ['required', 'string', new WhatsappValidPhoneNumber()],
            'flow.flow_id' => ['required', 'string', 'exists:whatsapp_flows,id'],
            'flow.header_text' => 'required|string',
            'flow.body_text' => 'required|string',
            'flow.footer_text' => 'required|string|max:60',
            'flow.flow_cta' => 'required|string',
            'screen' => 'required|string',
        ]);
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

    private function buildFlowPayload(Request $request, string $flowToken): array
    {
        return [
            'type' => 'flow',
            'header' => ['type' => 'text', 'text' => $request->input('flow.header_text')],
            'body' => ['text' => $request->input('flow.body_text')],
            'footer' => ['text' => $request->input('flow.footer_text')],
            'action' => [
                'name' => 'flow',
                'parameters' => [
                    'flow_message_version' => '3',
                    'flow_token' => $flowToken,
                    'flow_id' => $request->input('flow.flow_id'),
                    'flow_cta' => $request->input('flow.flow_cta'),
                    'flow_action' => 'navigate',
                    'flow_action_payload' => [
                        'screen' => $request->input('screen'),
                    ],
                ],
            ],
        ];
    }

    private function sendToApi(string $phoneNumberId, string $toPhone, array $flowPayload, string $accessToken): \Illuminate\Http\Client\Response
    {
        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $version = 'v23.0';
        $url = "{$baseUrl}/{$version}/{$phoneNumberId}/messages";

        $message = [
            'to' => $toPhone,
            'type' => WhatsappMessage::MESSAGE_TYPE_INTERACTIVE,
            'recipient_type' => 'individual',
            'messaging_product' => 'whatsapp',
            'interactive' => $flowPayload,
        ];

        return Http::withToken($accessToken)->post($url, $message);
    }

    private function saveMessage(Request $request, array $responseData, array $flowPayload, string $flowToken): WhatsappMessage
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
            'type' => WhatsappMessage::MESSAGE_TYPE_INTERACTIVE,
            'direction' => WhatsappMessage::MESSAGE_DIRECTION_SENT,
            'status' => WhatsappMessage::MESSAGE_STATUS_INITIATED,
            'conversation_id' => $request->input('conversation_id'),
        ]);

        // Create flow message record
        $flowMessage = WhatsappFlowMessage::create([
            'whatsapp_message_id' => $messageId,
            'whatsapp_flow_id' => $request->input('flow.flow_id'),
            'header_text' => $request->input('flow.header_text'),
            'body_text' => $request->input('flow.body_text'),
            'footer_text' => $request->input('flow.footer_text'),
            'flow_cta' => $request->input('flow.flow_cta'),
            'flow_token' => $flowToken,
            'screen_id' => $request->input('screen'),
        ]);

        // Update messageable relation
        $this->repository->update($message, [
            'messageable_id' => $flowMessage->id,
            'messageable_type' => WhatsappFlowMessage::class,
        ]);

        // Save initial status
        $this->repository->saveMessageStatus($messageId, WhatsappMessage::MESSAGE_STATUS_INITIATED);

        return $message->fresh(['messageable', 'statuses']);
    }
}
