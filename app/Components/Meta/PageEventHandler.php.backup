<?php

namespace App\Components\Meta;

use App\Models\MessengerConsumer;
use App\Models\MessengerMessage;
use App\Models\MessengerTextMessage;
use App\Models\MetaPage;
use App\Traits\ContactManager;
use App\Traits\ConversationManager;
use Log;

class PageEventHandler
{
    use ContactManager, ConversationManager;

    /** @var array */
    private array $notification;

    /**
     * EventHandler constructor.
     * @param array $notification
     */
    public function __construct(array $notification)
    {
        $this->notification = $notification;
    }

    /**
     * Handle the notification by iterating over entries and changes.
     *
     * @return void
     */
    public function handle(): void
    {
        if (empty($this->notification['entry'])) {
            Log::warning("No 'entry' found in the Messenger notification.");
            return;
        }

        foreach ($this->notification['entry'] as $entry) {
            $pageId = $entry['id'] ?? null;

            if (!$pageId) {
                Log::warning("No Page ID found in the entry.");
                continue;
            }

            if (empty($entry['messaging'])) {
                Log::warning("No 'messaging' field found for Page ID: $pageId");
                continue;
            }

            foreach ($entry['messaging'] as $event) {
                if (isset($event['message'])) {
                    $this->handleMessageEvent($pageId, $event);
                } else {
                    Log::info("Unhandled Messenger event", $event);
                }
            }
        }
    }

    private function handleMessageEvent(string $pageId, array $event): void
    {

        $senderId = $event['sender']['id'] ?? null;
        $recipientId = $event['recipient']['id'] ?? null;
        $timestamp = $event['timestamp'] ?? null;
        $message = $event['message'] ?? [];

        if (!$senderId || !$recipientId || !$message) {
            Log::warning("Invalid Messenger message payload", $event);
            return;
        }

        $psid = $senderId;
        $metaPage = MetaPage::where('id', $pageId)->first();


        if (!$metaPage) {
            Log::warning("MetaPage not found for ID: $pageId");
            return;
        }


        $accessToken = $metaPage->accessTokens()->first()?->access_token;

        // get the name here from APi or display name to be Facebook User
        $name = $this->getNameFromMessengerAPI(
            psid: $psid,
            pageId: $pageId,
            pageAccessToken: $accessToken
        );

        // 1. Find or create MessengerConsumer
        $messengerConsumer = MessengerConsumer::updateOrCreate(
            ['psid' => $psid, 'meta_page_id' => $metaPage->id],
            ['name' => $name] // name will be filled later if available
        );

        // 2. Find or create ContactEntity and link it
        $contact = $messengerConsumer->contact ?? $this->createContactFromMessengerConsumer(
            messengerConsumer: $messengerConsumer,
            workspaceId: $metaPage->workspace_id);

        $conversation = $this->startConversation(
            platform: "messenger",
            channel: $metaPage->channel,
            contact: $contact
        );

        // 3. Log or create a message model


        $type = $message['attachments'][0]['type'] ?? 'text'; // fallback to text if no type provided

        switch ($type) {
            case 'text':
                $this->handleMessengerTextMessage(
                    message: $message,
                    messengerConsumerID: $messengerConsumer->id,
                    metaPageID: $metaPage->id,
                    conversationID: $conversation?->id,
                    timestamp: $timestamp,
                );
                break;

            default:
                Log::info("Unhandled Messenger message type", ['type' => $type]);
                break;
        }
        // You can queue this step, or store it now.
        Log::info("Messenger message received", ['psid' => $psid, 'text' => $message['text'] ?? null]);

        // 4. Fire a frontend notification event later
    }

    private function handleMessengerTextMessage($message, $messengerConsumerID, $metaPageID, $conversationID, $timestamp): void
    {


        $messengerMessage = MessengerMessage::updateOrCreate([
            'id' => $message['mid']
        ], [
            'meta_page_id' => $metaPageID,
            'conversation_id' => $conversationID,
            'sender_type' => MessengerConsumer::class,
            'sender_id' => $messengerConsumerID,
            'recipient_type' => MetaPage::class,
            'recipient_id' => $metaPageID,
            'sender_role' => MessengerMessage::MESSAGE_SENDER_ROLE_CONSUMER,
            'type' => MessengerMessage::MESSAGE_TYPE_TEXT,
            'direction' => MessengerMessage::MESSAGE_DIRECTION_RECEIVED,
            'status' => MessengerMessage::MESSAGE_STATUS_DELIVERED,
            'messageable_id' => null,
            'messageable_type' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp
            ]);

        $text = $message['text'] ?? null;

        if ($text) {
            $textMessage = MessengerTextMessage::updateOrCreate([
                'messenger_message_id' => $message['mid']
            ], [
                'text' => $text,
            ]);

            // 3. Link it via polymorphic relation
            $messengerMessage->update([
                'messageable_type' => MessengerTextMessage::class,
                'messageable_id' => $textMessage->id,
            ]);
        }


    }

    /**
     * Handle unhandled fields.
     *
     * @param string $field
     * @return void
     */
    protected function handleUnhandledField(string $field): void
    {
        Log::info("Unhandled webhook field: $field");
    }

    /**
     * Get the handler method name based on the field.
     *
     * @param string $field
     * @return string
     */
    private function getHandlerMethod(string $field): string
    {
        return 'handle' . ucfirst($field) . 'Event';
    }


}
