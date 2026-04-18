<?php

namespace App\Domain\Conversation\Actions\WhatsApp;

use App\Constants\Meta;
use App\Domain\Conversation\DTOs\SendWhatsAppMessageDTO;
use App\Domain\Conversation\DTOs\WhatsAppMessageResultDTO;
use App\Domain\Conversation\Repositories\WhatsAppMessageRepositoryInterface;
use App\Models\WhatsappMessage;
use App\Models\WhatsappPhoneNumber;
use App\Models\WhatsappStickerMessage;
use App\Traits\BusinessTokenManager;
use App\Traits\WhatsappPhoneNumberManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendStickerMessageAction
{
    use BusinessTokenManager, WhatsappPhoneNumberManager;

    public function __construct(
        private WhatsAppMessageRepositoryInterface $repository
    ) {}

    public function execute(SendWhatsAppMessageDTO $dto): WhatsAppMessageResultDTO
    {
        try {
            /** 1️⃣ Validate input */
            if (empty($dto->content['id'])) {
                return WhatsAppMessageResultDTO::failure(
                    'Sticker media id is required',
                    422
                );
            }

            /** 2️⃣ Get access token */
            $accessToken = $this->getAccessToken($dto->fromPhoneNumberId);
            if (!$accessToken) {
                return WhatsAppMessageResultDTO::failure(
                    'Failed to get a valid access token',
                    401
                );
            }

            /** 3️⃣ Send to WhatsApp API */
            $payload  = $this->buildPayload($dto);
            $response = $this->sendToApi(
                $dto->fromPhoneNumberId,
                $payload,
                $accessToken
            );

            if (!$response->successful()) {
                return WhatsAppMessageResultDTO::failure(
                    'Failed to send sticker: ' .
                        ($response->json()['error']['message'] ?? 'Unknown error'),
                    $response->status()
                );
            }

            /** 4️⃣ Save message locally */
            $message = $this->saveMessage($dto, $response->json());

            return WhatsAppMessageResultDTO::success($message);
        } catch (\Throwable $e) {
            Log::error('SendStickerMessageAction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return WhatsAppMessageResultDTO::failure(
                'An error occurred while sending sticker',
                500
            );
        }
    }

    /**
     * Resolve access token
     */
    private function getAccessToken(string $phoneNumberId): ?string
    {
        $whatsappPhoneNumber = WhatsappPhoneNumber::find($phoneNumberId);
        if (!$whatsappPhoneNumber) {
            return null;
        }

        $businessAccount = $whatsappPhoneNumber->whatsappBusinessAccount;

        // Static token for Dreams SMS
        if ($businessAccount->name === 'Dreams SMS') {
            return Meta::ACCESS_TOKEN;
        }

        return $this->getValidAccessToken(
            $businessAccount->business_manager_account_id
        );
    }

    /**
     * Build WhatsApp API payload
     */
    private function buildPayload(SendWhatsAppMessageDTO $dto): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $dto->toPhoneNumber,
            'type'              => WhatsappMessage::MESSAGE_TYPE_STICKER,
            'sticker' => [
                'id' => $dto->content['id'],
            ],
        ];

        if ($dto->contextMessageId) {
            $payload['context'] = [
                'message_id' => $dto->contextMessageId,
            ];
        }

        return $payload;
    }

    /**
     * Send request to Meta API
     */
    private function sendToApi(
        string $phoneNumberId,
        array $payload,
        string $accessToken
    ): \Illuminate\Http\Client\Response {
        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $version = env('FACEBOOK_GRAPH_API_VERSION', 'v20.0');

        $url = "{$baseUrl}/{$version}/{$phoneNumberId}/messages";

        return Http::withToken($accessToken)->post($url, $payload);
    }

    /**
     * Persist message and sticker content
     */
    private function saveMessage(
        SendWhatsAppMessageDTO $dto,
        array $responseData
    ): WhatsappMessage {
        $messageId = $responseData['messages'][0]['id'];
        $waId      = $responseData['contacts'][0]['wa_id'];

        $whatsappPhoneNumber = WhatsappPhoneNumber::find(
            $dto->fromPhoneNumberId
        );

        $recipient = $this->repository->findOrCreateConsumer(
            $this->normalizePhoneNumber($dto->toPhoneNumber),
            $whatsappPhoneNumber->whatsapp_business_account_id,
            $waId
        );

        /** Create base whatsapp message */
        $message = $this->repository->create([
            'id'                        => $messageId,
            'whatsapp_phone_number_id'  => $dto->fromPhoneNumberId,
            'sender_type'               => WhatsappPhoneNumber::class,
            'sender_id'                 => $dto->fromPhoneNumberId,
            'agent_id'                  => $dto->agentId,
            'recipient_id'              => $recipient->id,
            'recipient_type'            => get_class($recipient),
            'sender_role'               => WhatsappMessage::MESSAGE_SENDER_ROLE_BUSINESS,
            'replied_to_message_id'     => $dto->contextMessageId,
            'type'                      => WhatsappMessage::MESSAGE_TYPE_STICKER,
            'direction'                 => WhatsappMessage::MESSAGE_DIRECTION_SENT,
            'status'                    => WhatsappMessage::MESSAGE_STATUS_INITIATED,
            'conversation_id'           => $dto->conversationId,
        ]);

        /** Create sticker messageable */
        $stickerMessage = $this->repository->createStickerMessage(
            $messageId,
            $dto->content['id'],
            $dto->content['is_animated'] ?? false,
            $dto->content['mime_type'] ?? null
        );

        /** Bind polymorphic relation */
        $this->repository->update($message, [
            'messageable_id'   => $stickerMessage->id,
            'messageable_type' => WhatsappStickerMessage::class,
        ]);

        /** Save initial status */
        $this->repository->saveMessageStatus(
            $messageId,
            WhatsappMessage::MESSAGE_STATUS_INITIATED
        );

        return $message->fresh(['messageable', 'statuses']);
    }
}
