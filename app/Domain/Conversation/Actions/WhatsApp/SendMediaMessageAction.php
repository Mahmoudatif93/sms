<?php

namespace App\Domain\Conversation\Actions\WhatsApp;

use App\Constants\Meta;
use App\Domain\Conversation\DTOs\SendWhatsAppMessageDTO;
use App\Domain\Conversation\DTOs\WhatsAppMessageResultDTO;
use App\Domain\Conversation\Repositories\WhatsAppMessageRepositoryInterface;
use App\Models\WhatsappAudioMessage;
use App\Models\WhatsappDocumentMessage;
use App\Models\WhatsappImageMessage;
use App\Models\WhatsappMessage;
use App\Models\WhatsappPhoneNumber;
use App\Models\WhatsappVideoMessage;
use App\Traits\BusinessTokenManager;
use App\Traits\WhatsappPhoneNumberManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendMediaMessageAction
{
    use BusinessTokenManager, WhatsappPhoneNumberManager;

    public function __construct(
        private WhatsAppMessageRepositoryInterface $repository
    ) {}

    public function execute(SendWhatsAppMessageDTO $dto): WhatsAppMessageResultDTO
    {
        try {
            $accessToken = $this->getAccessToken($dto->fromPhoneNumberId);
            if (!$accessToken) {
                return WhatsAppMessageResultDTO::failure('Failed to get a valid access token', 401);
            }

            $payload = $this->buildPayload($dto);
            $response = $this->sendToApi($dto->fromPhoneNumberId, $payload, $accessToken);

            if (!$response->successful()) {
                return WhatsAppMessageResultDTO::failure(
                    'Failed to send media: ' . ($response->json()['error']['message'] ?? 'Unknown error'),
                    $response->status()
                );
            }

            $message = $this->saveMessage($dto, $response->json());

            return WhatsAppMessageResultDTO::success($message);

        } catch (\Exception $e) {
            Log::error('SendMediaMessageAction failed', [
                'type' => $dto->type,
                'error' => $e->getMessage(),
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

    private function buildPayload(SendWhatsAppMessageDTO $dto): array
    {
        $payload = [
            'to' => $dto->toPhoneNumber,
            'type' => $dto->type,
            'recipient_type' => 'individual',
            'messaging_product' => 'whatsapp',
            $dto->type => $dto->content,
        ];

        if ($dto->contextMessageId) {
            $payload['context'] = ['message_id' => $dto->contextMessageId];
        }

        return $payload;
    }

    private function sendToApi(string $phoneNumberId, array $payload, string $accessToken): \Illuminate\Http\Client\Response
    {
        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $version = env('FACEBOOK_GRAPH_API_VERSION', 'v20.0');
        $url = "{$baseUrl}/{$version}/{$phoneNumberId}/messages";

        return Http::withToken($accessToken)->post($url, $payload);
    }

    private function saveMessage(SendWhatsAppMessageDTO $dto, array $responseData): WhatsappMessage
    {
        $messageId = $responseData['messages'][0]['id'];
        $waId = $responseData['contacts'][0]['wa_id'];

        $whatsappPhoneNumber = WhatsappPhoneNumber::find($dto->fromPhoneNumberId);
        $businessAccountId = $whatsappPhoneNumber->whatsapp_business_account_id;

        $recipient = $this->repository->findOrCreateConsumer(
            $this->normalizePhoneNumber($dto->toPhoneNumber),
            $businessAccountId,
            $waId
        );

        $message = $this->repository->create([
            'id' => $messageId,
            'whatsapp_phone_number_id' => $dto->fromPhoneNumberId,
            'sender_type' => WhatsappPhoneNumber::class,
            'sender_id' => $dto->fromPhoneNumberId,
            'agent_id' => $dto->agentId,
            'recipient_id' => $recipient->id,
            'recipient_type' => get_class($recipient),
            'sender_role' => WhatsappMessage::MESSAGE_SENDER_ROLE_BUSINESS,
            'replied_to_message_id' => $dto->contextMessageId,
            'type' => $dto->type,
            'direction' => WhatsappMessage::MESSAGE_DIRECTION_SENT,
            'status' => WhatsappMessage::MESSAGE_STATUS_INITIATED,
            'conversation_id' => $dto->conversationId,
        ]);

        // Create type-specific content
        $mediaMessage = $this->createMediaContent($dto, $messageId);
        dd($mediaMessage);
        $this->repository->update($message, [
            'messageable_id' => $mediaMessage->id,
            'messageable_type' => get_class($mediaMessage),
        ]);

        $this->repository->saveMessageStatus($messageId, WhatsappMessage::MESSAGE_STATUS_INITIATED);

        return $message->fresh(['messageable', 'statuses']);
    }

    private function createMediaContent(SendWhatsAppMessageDTO $dto, string $messageId): mixed
    {
        dd('2');
        return match ($dto->type) {
            'image' => $this->repository->createImageMessage(
                $messageId,
                $dto->content['id'] ?? null,
                $dto->content['caption'] ?? null
            ),
            'video' => $this->repository->createVideoMessage(
                $messageId,
                $dto->content['id'] ?? null,
                $dto->content['caption'] ?? null
            ),
            'audio' => $this->repository->createAudioMessage(
                $messageId,
                $dto->content['id'] ?? null
            ),
            'document' => $this->repository->createDocumentMessage(
                $messageId,
                $dto->content['id'] ?? null,
                $dto->content['filename'] ?? null,
                $dto->content['caption'] ?? null
            ),
            default => throw new \InvalidArgumentException("Unsupported media type: {$dto->type}"),
        };
    }
}
