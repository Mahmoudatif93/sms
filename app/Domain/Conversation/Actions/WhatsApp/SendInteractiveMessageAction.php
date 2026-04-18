<?php

namespace App\Domain\Conversation\Actions\WhatsApp;

use App\Constants\Meta;
use App\Domain\Conversation\DTOs\WhatsAppMessageResultDTO;
use App\Domain\Conversation\Repositories\WhatsAppMessageRepositoryInterface;
use App\Models\Conversation;
use App\Models\WhatsappInteractiveMessage;
use App\Models\WhatsappMessage;
use App\Models\WhatsappPhoneNumber;
use App\Traits\BusinessTokenManager;
use App\Traits\WhatsappPhoneNumberManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SendInteractiveMessageAction
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
            $conversationId = $request->input('conversation_id');

            // Resolve recipient phone number
            $toPhoneNumber = $this->resolveRecipientPhone($request, $conversationId);
            if (!$toPhoneNumber) {
                return WhatsAppMessageResultDTO::failure('Recipient phone number not found', 400);
            }

            // Get access token
            $accessToken = $this->getAccessToken($fromPhoneNumberId);
            if (!$accessToken) {
                return WhatsAppMessageResultDTO::failure('Failed to get a valid access token', 401);
            }

            // Prepare interactive payload
            $interactivePayload = $this->prepareInteractivePayload($request->input('interactive'));

            // Send to WhatsApp API
            $response = $this->sendToApi($fromPhoneNumberId, $toPhoneNumber, $interactivePayload, $accessToken);

            if (!$response->successful()) {
                return WhatsAppMessageResultDTO::failure(
                    'Failed to send message: ' . ($response->json()['error']['message'] ?? 'Unknown error'),
                    $response->status()
                );
            }

            // Save message to database
            $message = $this->saveMessage($request, $response->json(), $toPhoneNumber, $interactivePayload);

            return WhatsAppMessageResultDTO::success($message);

        } catch (\Exception $e) {
            Log::error('SendInteractiveMessageAction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return WhatsAppMessageResultDTO::failure('An error occurred: ' . $e->getMessage(), 500);
        }
    }

    private function validateRequest(Request $request): \Illuminate\Validation\Validator
    {
        $toField = $request->input('to');
        $isToString = is_string($toField);

        return Validator::make($request->all(), [
            'from' => ['required', 'string', 'exists:whatsapp_phone_numbers,id'],
            'to' => $isToString ? ['required', 'string'] : ['nullable', 'array'],
            'conversation_id' => ['required', 'string', 'exists:conversations,id'],
            'interactive' => 'required|array',
            'interactive.type' => ['required', 'string', 'in:button,list'],
            'interactive.body' => 'required|array',
            'interactive.body.text' => 'required|string|max:1024',
            'interactive.action' => 'required|array',
        ]);
    }

    private function resolveRecipientPhone(Request $request, string $conversationId): ?string
    {
        $toField = $request->input('to');

        if (is_string($toField)) {
            return $toField;
        }

        if (is_array($toField) && isset($toField['contact'])) {
            return $toField['contact'];
        }

        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            return null;
        }

        return $conversation->whatsapp_consumer_phone_number->phone_number ?? null;
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

    private function prepareInteractivePayload(array $interactive): array
    {
        $payload = [
            'type' => $interactive['type'],
            'body' => $interactive['body'],
        ];

        if (isset($interactive['header'])) {
            $payload['header'] = $interactive['header'];
        }

        if (isset($interactive['footer'])) {
            $payload['footer'] = $interactive['footer'];
        }

        if ($interactive['type'] === 'button' && isset($interactive['action']['buttons'])) {
            $payload['action'] = [
                'buttons' => array_map(function ($button) {
                    return [
                        'type' => 'reply',
                        'reply' => [
                            'id' => $button['id'],
                            'title' => $button['title'],
                        ],
                    ];
                }, $interactive['action']['buttons']),
            ];
        } elseif ($interactive['type'] === 'list') {
            $payload['action'] = $interactive['action'];
        }

        return $payload;
    }

    private function sendToApi(string $phoneNumberId, string $toPhone, array $interactivePayload, string $accessToken): \Illuminate\Http\Client\Response
    {
        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $version = env('FACEBOOK_GRAPH_API_VERSION', 'v20.0');
        $url = "{$baseUrl}/{$version}/{$phoneNumberId}/messages";

        $message = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toPhone,
            'type' => 'interactive',
            'interactive' => $interactivePayload,
        ];

        return Http::withToken($accessToken)->post($url, $message);
    }

    private function saveMessage(Request $request, array $responseData, string $toPhoneNumber, array $interactivePayload): WhatsappMessage
    {
        $messageId = $responseData['messages'][0]['id'];
        $waId = $responseData['contacts'][0]['wa_id'];
        $fromPhoneNumberId = $request->input('from');

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

        // Create interactive message record
        $interactiveMessage = WhatsappInteractiveMessage::create([
            'whatsapp_message_id' => $messageId,
            'interactive_type' => $request->input('interactive.type'),
            'payload' => $interactivePayload,
        ]);

        // Update messageable relation
        $this->repository->update($message, [
            'messageable_id' => $interactiveMessage->id,
            'messageable_type' => WhatsappInteractiveMessage::class,
        ]);

        // Save initial status
        $this->repository->saveMessageStatus($messageId, WhatsappMessage::MESSAGE_STATUS_INITIATED);

        return $message->fresh(['messageable', 'statuses']);
    }
}
