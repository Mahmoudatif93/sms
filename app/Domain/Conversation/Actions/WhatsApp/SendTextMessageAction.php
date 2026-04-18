<?php

namespace App\Domain\Conversation\Actions\WhatsApp;

use App\Constants\Meta;
use App\Domain\Conversation\DTOs\SendWhatsAppMessageDTO;
use App\Domain\Conversation\DTOs\WhatsAppMessageResultDTO;
use App\Domain\Conversation\Repositories\WhatsAppMessageRepositoryInterface;
use App\Enums\WalletTransactionStatus;
use App\Models\MessageBilling;
use App\Models\WalletTransaction;
use App\Models\WhatsappMessage;
use App\Models\WhatsappPhoneNumber;
use App\Models\WhatsappTextMessage;
use App\Traits\BusinessTokenManager;
use App\Traits\WhatsappPhoneNumberManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendTextMessageAction
{
    use BusinessTokenManager, WhatsappPhoneNumberManager;

    public function __construct(
        private WhatsAppMessageRepositoryInterface $repository
    ) {}

    public function execute(SendWhatsAppMessageDTO $dto): WhatsAppMessageResultDTO
    {
        try {
            // Get access token
            $accessToken = $this->getAccessToken($dto->fromPhoneNumberId);
            if (!$accessToken) {
                return WhatsAppMessageResultDTO::failure('Failed to get a valid access token', 401);
            }

            // Build message payload
            $payload = $this->buildPayload($dto);

            // Send to WhatsApp API
            $response = $this->sendToApi($dto->fromPhoneNumberId, $payload, $accessToken);
            if (!$response->successful()) {
                return WhatsAppMessageResultDTO::failure(
                    'Failed to send message: ' . ($response->json()['error']['message'] ?? 'Unknown error'),
                    $response->status()
                );
            }

            // Save message to database
            $message = $this->saveMessage($dto, $response->json());

            return WhatsAppMessageResultDTO::success($message);

        } catch (\Exception $e) {
            Log::error('SendTextMessageAction failed', [
                'error' => $e->getMessage(),
                'dto' => $dto,
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
            'type' => WhatsappMessage::MESSAGE_TYPE_TEXT,
            'recipient_type' => 'individual',
            'messaging_product' => 'whatsapp',
            'text' => [
                'preview_url' => $dto->content['preview_url'] ?? false,
                'body' => $dto->content['body'],
            ],
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

        // Get or create recipient
        $recipient = $this->repository->findOrCreateConsumer(
            $this->normalizePhoneNumber($dto->toPhoneNumber),
            $businessAccountId,
            $waId
        );

        // Create main message
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
            'type' => WhatsappMessage::MESSAGE_TYPE_TEXT,
            'direction' => WhatsappMessage::MESSAGE_DIRECTION_SENT,
            'status' => WhatsappMessage::MESSAGE_STATUS_INITIATED,
            'conversation_id' => $dto->conversationId,
        ]);

        // Create text message content (save original text if available)
        $textMessage = $this->repository->createTextMessage(
            $messageId,
            $dto->originalText ?? $dto->content['body'],
            $dto->content['preview_url'] ?? null
        );

        // Update messageable relation
        $this->repository->update($message, [
            'messageable_id' => $textMessage->id,
            'messageable_type' => WhatsappTextMessage::class,
        ]);

        // Handle translation billing
        if ($dto->originalText && $dto->content['body'] !== $dto->originalText) {
            $this->handleTranslationBilling($dto, $message, $dto->content['body']);
        }

        // Save initial status
        $this->repository->saveMessageStatus($messageId, WhatsappMessage::MESSAGE_STATUS_INITIATED);

        return $message->fresh(['messageable', 'statuses']);
    }

    private function handleTranslationBilling(SendWhatsAppMessageDTO $dto, WhatsappMessage $message, string $translatedText): void
    {
        $this->repository->createTranslation(
            $message->id,
            WhatsappMessage::class,
            $translatedText,
            $dto->translatedTo
        );

        if ($dto->translationTransactionId) {
            $transaction = WalletTransaction::find($dto->translationTransactionId);
            if ($transaction && $transaction->status === WalletTransactionStatus::PENDING) {
                $wallet = $transaction->wallet()->lockForUpdate()->first();
                $wallet->pending_amount -= abs($transaction->amount);
                $wallet->amount -= abs($transaction->amount);
                $wallet->save();

                $transaction->status = WalletTransactionStatus::ACTIVE;
                $transaction->description = 'Confirmed Translation';
                $transaction->save();

                MessageBilling::create([
                    'messageable_id' => $message->id,
                    'messageable_type' => WhatsappMessage::class,
                    'type' => MessageBilling::TYPE_TRANSLATION,
                    'cost' => $transaction->amount * -1,
                    'is_billed' => true,
                ]);
            }
        }
    }
}
