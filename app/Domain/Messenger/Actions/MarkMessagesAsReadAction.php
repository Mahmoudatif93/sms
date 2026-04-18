<?php

namespace App\Domain\Messenger\Actions;

use App\Domain\Messenger\Repositories\MessengerMessageRepositoryInterface;
use App\Models\Conversation;
use App\Models\MetaPage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MarkMessagesAsReadAction
{
    public function __construct(
        private MessengerMessageRepositoryInterface $repository
    ) {}

    public function execute(Conversation $conversation): array
    {
        $channel = $conversation->channel;
        $connector = $channel->connector;
        $messengerConfiguration = $connector->messengerConfiguration;

        if (!$messengerConfiguration || !$messengerConfiguration->meta_page_id) {
            return [
                'success' => false,
                'error' => 'Messenger Configuration is missing or incomplete',
                'marked_count' => 0,
            ];
        }

        $metaPage = $this->repository->findMetaPage($messengerConfiguration->meta_page_id);

        if (!$metaPage) {
            return [
                'success' => false,
                'error' => 'Meta Page not found',
                'marked_count' => 0,
            ];
        }

        $contact = $conversation->contact;
        if (!$contact) {
            return [
                'success' => false,
                'error' => 'Contact information is missing for this conversation',
                'marked_count' => 0,
            ];
        }

        $messengerConsumer = $contact->messengerConsumers()->where('meta_page_id', $metaPage->id)->first();

        if (!$messengerConsumer) {
            return [
                'success' => false,
                'error' => 'Messenger consumer not found for this contact',
                'marked_count' => 0,
            ];
        }

        // Get unread messages
        $unreadMessages = $this->repository->getUnreadReceivedMessages($conversation->id);

        if ($unreadMessages->isEmpty()) {
            return [
                'success' => true,
                'message' => 'No unread Messenger messages found',
                'marked_count' => 0,
            ];
        }

        // Send mark_seen to Facebook API (only need to send once)
        $this->sendMarkSeenToApi($metaPage, $messengerConsumer->psid);

        // Mark messages as read in database
        $count = 0;
        foreach ($unreadMessages as $message) {
            if ($this->repository->markMessageAsRead($message->id)) {
                $count++;
            }
        }

        return [
            'success' => true,
            'message' => "{$count} Messenger messages marked as read",
            'marked_count' => $count,
        ];
    }

    private function sendMarkSeenToApi(MetaPage $metaPage, string $psid): void
    {
        $accessToken = $this->repository->getPageAccessToken($metaPage);

        if (!$accessToken) {
            Log::error('Failed to get access token for marking Messenger messages as seen');
            return;
        }

        try {
            $response = Http::post("https://graph.facebook.com/v24.0/{$metaPage->id}/messages", [
                'recipient' => ['id' => $psid],
                'sender_action' => 'mark_seen',
                'access_token' => $accessToken,
            ]);

            if (!$response->successful()) {
                Log::error('Failed to mark Messenger messages as seen via API', [
                    'page_id' => $metaPage->id,
                    'psid' => $psid,
                    'status_code' => $response->status(),
                    'response' => $response->json(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception while marking Messenger messages as seen', [
                'page_id' => $metaPage->id,
                'psid' => $psid,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
