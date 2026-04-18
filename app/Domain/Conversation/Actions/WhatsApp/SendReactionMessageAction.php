<?php

namespace App\Domain\Conversation\Actions\WhatsApp;

use App\Constants\Meta;
use App\Domain\Conversation\DTOs\SendWhatsAppMessageDTO;
use App\Domain\Conversation\DTOs\WhatsAppMessageResultDTO;
use App\Domain\Conversation\Repositories\WhatsAppMessageRepositoryInterface;
use App\Models\WhatsappMessage;
use App\Models\WhatsappPhoneNumber;
use App\Models\WhatsappReactionMessage;
use App\Traits\BusinessTokenManager;
use App\Traits\WhatsappPhoneNumberManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendReactionMessageAction
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

            $emoji = $dto->content['emoji'] ?? '';
            $isRemoval = $emoji === '';

            $payload = $this->buildPayload($dto);
            $response = $this->sendToApi($dto->fromPhoneNumberId, $payload, $accessToken);

            if (!$response->successful()) {
                return WhatsAppMessageResultDTO::failure(
                    'Failed to send reaction: ' . ($response->json()['error']['message'] ?? 'Unknown error'),
                    $response->status()
                );
            }

            // If removing reaction, delete from database
            if ($isRemoval) {
                $this->repository->deleteReactionByMessageId($dto->content['message_id']);
                return WhatsAppMessageResultDTO::success(
                    $this->repository->findById($dto->content['message_id']) ?? new WhatsappMessage()
                );
            }

            $message = $this->saveMessage($dto, $response->json());

            return WhatsAppMessageResultDTO::success($message);

        } catch (\Exception $e) {
            Log::error('SendReactionMessageAction failed', [
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
        return [
            'to' => $dto->toPhoneNumber,
            'type' => 'reaction',
            'recipient_type' => 'individual',
            'messaging_product' => 'whatsapp',
            'reaction' => [
                'message_id' => $dto->content['message_id'],
                'emoji' => $dto->content['emoji'] ?? '',
            ],
        ];
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
            'type' => 'reaction',
            'direction' => WhatsappMessage::MESSAGE_DIRECTION_SENT,
            'status' => WhatsappMessage::MESSAGE_STATUS_INITIATED,
            'conversation_id' => $dto->conversationId,
        ]);

        $reactionMessage = $this->repository->createReactionMessage(
            $dto->content['message_id'],
            $messageId,
            $dto->content['emoji'] ?? '',
            WhatsappMessage::MESSAGE_DIRECTION_SENT
        );

        $this->repository->update($message, [
            'messageable_id' => $reactionMessage->id,
            'messageable_type' => WhatsappReactionMessage::class,
        ]);

        $this->repository->saveMessageStatus($messageId, WhatsappMessage::MESSAGE_STATUS_INITIATED);

        return $message->fresh(['messageable', 'statuses']);
    }
}
