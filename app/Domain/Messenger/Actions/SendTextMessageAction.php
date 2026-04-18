<?php

namespace App\Domain\Messenger\Actions;

use App\Domain\Messenger\DTOs\SendTextMessageDTO;
use App\Domain\Messenger\Repositories\MessengerMessageRepositoryInterface;
use App\Models\MessengerConsumer;
use App\Models\MessengerMessage;
use App\Models\MetaPage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendTextMessageAction
{
    public function __construct(
        private MessengerMessageRepositoryInterface $repository
    ) {}

    public function execute(SendTextMessageDTO $dto): array
    {
        // Find MetaPage
        $metaPage = $this->repository->findMetaPage($dto->pageId);

        if (!$metaPage) {
            return [
                'success' => false,
                'error' => 'Meta Page not found',
                'message' => null,
            ];
        }

        // Get access token
        $accessToken = $this->repository->getPageAccessToken($metaPage);

        if (!$accessToken) {
            return [
                'success' => false,
                'error' => 'Failed to get a valid access token',
                'message' => null,
            ];
        }

        // Find consumer
        $consumer = $this->repository->findConsumerByPsid($dto->recipientPsid, $metaPage->id);

        if (!$consumer) {
            return [
                'success' => false,
                'error' => 'Messenger consumer not found',
                'message' => null,
            ];
        }

        // Send message via Facebook Graph API
        $apiResponse = $this->sendToFacebookApi($metaPage, $dto, $accessToken);

        if (!$apiResponse['success']) {
            return [
                'success' => false,
                'error' => $apiResponse['error'],
                'message' => null,
            ];
        }

        // Create message record in database
        $messengerMessage = $this->createMessageRecord(
            $apiResponse['message_id'],
            $metaPage,
            $consumer,
            $dto
        );

        // Save initial status
        $this->repository->saveMessageStatus($messengerMessage->id, MessengerMessage::MESSAGE_STATUS_SENT);

        // Create text content
        $textMessage = $this->repository->createTextMessage($messengerMessage->id, $dto->text);

        // Update messageable relation
        $this->repository->updateMessageable($messengerMessage->id, $textMessage);

        // Reload message with relations
        $messageWithRelations = $this->repository->findMessage($messengerMessage->id);

        return [
            'success' => true,
            'error' => null,
            'message' => $messageWithRelations,
        ];
    }

    private function sendToFacebookApi(MetaPage $metaPage, SendTextMessageDTO $dto, string $accessToken): array
    {
        try {
            $url = "https://graph.facebook.com/v24.0/{$metaPage->id}/messages";

            $payload = [
                'recipient' => ['id' => $dto->recipientPsid],
                'messaging_type' => $dto->messagingType,
                'message' => ['text' => $dto->text],
            ];

            // Add reply_to if replying to a specific message
            if ($dto->hasReplyTo()) {
                $payload['reply_to'] = ['mid' => $dto->replyToMessageId];
            }

            $response = Http::withToken($accessToken)->post($url, $payload);

            if (!$response->successful()) {
                $errorData = $response->json();
                $errorMessage = $errorData['error']['message'] ?? 'Unknown error from Facebook API';

                Log::error('Failed to send Messenger text message', [
                    'page_id' => $metaPage->id,
                    'psid' => $dto->recipientPsid,
                    'status_code' => $response->status(),
                    'response' => $errorData,
                ]);

                return [
                    'success' => false,
                    'error' => $errorMessage,
                    'message_id' => null,
                ];
            }

            $responseData = $response->json();

            return [
                'success' => true,
                'error' => null,
                'message_id' => $responseData['message_id'] ?? null,
                'recipient_id' => $responseData['recipient_id'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Exception while sending Messenger text message', [
                'page_id' => $metaPage->id,
                'psid' => $dto->recipientPsid,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message_id' => null,
            ];
        }
    }

    private function createMessageRecord(
        string $messageId,
        MetaPage $metaPage,
        MessengerConsumer $consumer,
        SendTextMessageDTO $dto
    ): MessengerMessage {
        return $this->repository->createOutgoingMessage([
            'id' => $messageId,
            'meta_page_id' => $metaPage->id,
            'conversation_id' => $dto->conversationId,
            'sender_type' => MetaPage::class,
            'sender_id' => $metaPage->id,
            'recipient_type' => MessengerConsumer::class,
            'recipient_id' => $consumer->id,
            'sender_role' => MessengerMessage::MESSAGE_SENDER_ROLE_BUSINESS,
            'type' => MessengerMessage::MESSAGE_TYPE_TEXT,
            'direction' => MessengerMessage::MESSAGE_DIRECTION_SENT,
            'status' => MessengerMessage::MESSAGE_STATUS_SENT,
            'replied_to_message_id' => $dto->replyToMessageId,
        ]);
    }
}
