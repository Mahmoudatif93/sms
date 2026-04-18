<?php

namespace App\Components\Meta;

use App\Constants\Meta;
use App\Events\UnifiedMessageEvent;
// use App\Events\UnifiedMessageEvent;
use App\Events\WhatsappMessageStatusUpdated;
use App\Http\Responses\ConversationMessage;
use App\Logging\MetaConversationTextLogs;
use App\Models\Channel;
use App\Models\MetaConversationLog;
use App\Models\WhatsappAudioMessage;
use App\Models\WhatsappConsumerPhoneNumber;
use App\Models\WhatsappConversation;
use App\Models\WhatsappFlowResponseMessage;
use App\Models\WhatsappImageMessage;
use App\Models\WhatsappInteractiveMessage;
use App\Models\WhatsappMessage;
use App\Models\WhatsappMessageStatus;
use App\Models\WhatsappMessageStatusError;
use App\Models\WhatsappPhoneNumber;
use App\Models\WhatsappTextMessage;
use App\Models\WhatsappVideoMessage;
use App\Models\WhatsappReactionMessage;
use App\Models\WhatsappDocumentMessage;
use App\Traits\AutoTranslationHandler;
use App\Traits\ConversationManager;
use App\Traits\WhatsappBillingTrait;
use App\Traits\WhatsappMediaManager;
use App\Traits\WhatsappPhoneNumberManager;
use App\Traits\WhatsappWalletManager;
use Illuminate\Support\Facades\Http;
use Log;

class WhatsappBusinessAccountEventHandler
{

    use WhatsappPhoneNumberManager, WhatsappMediaManager, WhatsappBillingTrait, ConversationManager, WhatsappWalletManager, AutoTranslationHandler;

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

        // Check if 'entry' exists and is not empty
        if (empty($this->notification['entry'])) {
            Log::warning("No 'entry' found in the notification.");
            return;
        }

        foreach ($this->notification['entry'] as $entry) {
            // Validate if 'id' is set for WhatsApp Business Account
            if (empty($entry['id'])) {
                Log::warning("No 'id' found in the entry.");
                continue; // Skip this entry if 'id' is missing
            }

            $whatsappBusinessAccountID = $entry['id'];

            // Check if 'changes' exists and is not empty
            if (empty($entry['changes'])) {
                Log::warning("No 'changes' found for WhatsApp Business Account ID: $whatsappBusinessAccountID");
                continue; // Skip if no 'changes' found
            }
            foreach ($entry['changes'] as $change) {
                // Validate if 'field' and 'value' are present
                if (empty($change['field']) || empty($change['value'])) {
                    Log::warning("Invalid 'change' structure. 'field' or 'value' is missing for WhatsApp Business Account ID: $whatsappBusinessAccountID");
                    continue; // Skip if 'field' or 'value' is missing
                }

                $field = $change['field'];
                $value = $change['value'];
                // Check if the corresponding handler method exists
                $method = $this->getHandlerMethod($field);
                if (method_exists($this, $method)) {
                    $this->$method($whatsappBusinessAccountID, $value);
                } else {
                    $this->handleUnhandledField($field);
                }
            }
        }
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
     * Handle 'messages' events.
     *
     * @param array $value
     * @return void
     */
    protected function handleMessagesEvent(string $whatsappBusinessAccountID, array $value): void
    {
        // Validate metadata and phone number ID
        if (empty($value['metadata']['phone_number_id'])) {
            Log::warning("Missing phone number ID in metadata for WhatsApp Business Account ID: $whatsappBusinessAccountID");
            return;
        }
        $phoneNumberID = $value['metadata']['phone_number_id'];

        // Handle statuses
        $statuses = $value['statuses'] ?? null;
        if (!empty($statuses)) {
            $this->handleStatuses($statuses, $phoneNumberID);
        }

        // Handle incoming messages (only text messages)
        $messages = $value['messages'] ?? [];
        if (!empty($messages)) {
            $contactsMap = $this->mapContacts($value['contacts'] ?? []);
            foreach ($messages as $message) {
                $messageType = $message['type'] ?? null;
                $context = $message['context'] ?? null;
                switch ($messageType) {
                    case 'text':
                        $this->handleTextMessages([$message], $contactsMap, $whatsappBusinessAccountID, $phoneNumberID);
                        break;
                    case 'image':
                        $this->handleImageMessages($message, $contactsMap, $whatsappBusinessAccountID, $phoneNumberID);
                        break;
                    case 'video':
                        $this->handleVideoMessages($message, $contactsMap, $whatsappBusinessAccountID, $phoneNumberID);
                        break;
                    case 'audio':
                        $this->handleAudioMessages($message, $contactsMap, $whatsappBusinessAccountID, $phoneNumberID);
                        break;
                    case 'document':
                        $this->handleDocumentMessages($message, $contactsMap, $whatsappBusinessAccountID, $phoneNumberID);
                        break;
                    case 'interactive':
                        $this->handleInteractiveMessages($message, $contactsMap, $whatsappBusinessAccountID, $phoneNumberID);
                        break;
                    case 'button':
                        $this->handleButtonMessages($message, $contactsMap, $whatsappBusinessAccountID, $phoneNumberID);
                        break;
                    case 'reaction':
                        $this->handleReactionMessages($message, $contactsMap, $whatsappBusinessAccountID, $phoneNumberID);
                        break;
                    // Add more cases for other media types like 'video', 'audio', etc.
                    default:
                        Log::info("Unhandled message type: $messageType");
                        break;
                }
            }
            //     $this->handleTextMessages($messages, $contactsMap, $whatsappBusinessAccountID, $phoneNumberID);
        }

        Log::info("Handled message event", $value);
    }

    private function handleStatuses(array $statuses, string $phoneNumberID): void
    {
        foreach ($statuses as $status) {
            // Validate message ID
            if (empty($status['id'])) {
                Log::warning("Missing message ID in status.");
                continue;
            }

            $whatsappMessageID = $status['id'];
            $whatsappMessage = WhatsappMessage::find($whatsappMessageID);
            if ($whatsappMessage) {
                // Update message status
                $whatsappMessage->update(['status' => $status['status'], ['whatsapp_conversation_id' => $status['conversation']['id'] ?? null]]);

                $this->finalizeWhatsappWalletTransaction($whatsappMessage, $status['status']);

                // Handle conversation
                if (!empty($status['conversation']['id'])) {
                    // $this->handleConversation($status, $whatsappMessage, $phoneNumberID);
                }

                // Store or update message status
                $whatsappMessageStatus = WhatsappMessageStatus::updateOrCreate(
                    [
                        'whatsapp_message_id' => $whatsappMessageID,
                        'status' => $status['status']
                    ],
                    [
                        'timestamp' => $status['timestamp']
                    ]
                );

                // Check for errors in the status
                if (isset($status['errors']) && is_array($status['errors'])) {
                    foreach ($status['errors'] as $error) {
                        WhatsappMessageStatusError::create([
                            'whatsapp_message_status_id' => $whatsappMessageStatus->id,
                            'error_code' => $error['code'] ?? null,
                            'error_title' => $error['title'] ?? null,
                            'error_message' => $error['message'] ?? null,
                            'error_details' => $error['error_data']['details'] ?? null,
                        ]);

                        if ($status['status'] === 'sent') {
                            $this->logMetaConversation('sent_successful', $whatsappMessage, $status);
                        } elseif ($status['status'] === 'delivered') {
                            $this->logMetaConversation('delivered_successful', $whatsappMessage);
                        } elseif ($status['status'] === 'read') {
                            $this->logMetaConversation('read_by_user', $whatsappMessage);
                        }


                        // Also log this into MetaConversationLog if it's 131047
                        if (($error['code'] ?? null) === 131047) {
                            MetaConversationLog::create([
                                'conversation_id' => $whatsappMessage->conversation_id,
                                'whatsapp_message_id' => $whatsappMessage->id,
                                'whatsapp_conversation_id' => $whatsappMessage->whatsapp_conversation_id,
                                'decision' => 'csw_closed_failed',
                                'category_attempted' => optional($whatsappMessage->template?->whatsappTemplate)?->category, // if exists
                                'message_type' => $whatsappMessage->type,
                                'direction' => $whatsappMessage->direction,
                                'was_blocked' => true,
                                'meta_error_code' => 131047,
                                'meta_error_message' => $error['error_data']['details'] ?? 'Re-engagement message',
                                'text_log' => MetaConversationTextLogs::get('csw_closed_failed')
                            ]);
                        }
                    }
                }

                event(new WhatsappMessageStatusUpdated($whatsappMessage));


            } else {
                Log::warning("Message with ID $whatsappMessageID not found.");
            }
        }
    }

    private function handleConversation(array $status, WhatsappMessage $whatsappMessage, string $phoneNumberID): void
    {
        $conversationID = $status['conversation']['id'] ?? null;
        $recipientID = $status['recipient_id'] ?? null;

        if (!$conversationID || !$recipientID) {
            Log::warning("Missing conversation ID or recipient ID.");
            return;
        }

        // Find the existing conversation by its ID
        $conversation = WhatsappConversation::find($conversationID);

        // Retrieve the business account ID and the contact's name (if available)
        $whatsappBusinessAccountID = $whatsappMessage->whatsappPhoneNumber->whatsapp_business_account_id ?? null;
        $contactName = $status['contact_name'] ?? null;

        // Normalize the recipient's phone number
        $recipientPhoneNumber = $this->normalizePhoneNumber($recipientID);

        // First, find or create the consumer phone number associated with the business account
        $consumerPhoneNumber = WhatsappConsumerPhoneNumber::firstOrCreate(
            [
                'phone_number' => $recipientPhoneNumber,
                'whatsapp_business_account_id' => $whatsappBusinessAccountID,
            ],
            [
                'wa_id' => $recipientID,
                'name' => $contactName,
            ]
        );


        // If the conversation doesn't exist, create a new one
        if (!$conversation) {
            WhatsappConversation::create([
                'id' => $conversationID,
                'whatsapp_phone_number_id' => $phoneNumberID,
                'type' => $status['conversation']['origin']['type'] ?? null,
                'expiration_timestamp' => $status['conversation']['expiration_timestamp'] ?? null,
                'whatsapp_consumer_phone_number_id' => $consumerPhoneNumber->id  // Get the ID of the consumer phone number
            ]);
        } else {
            // Only update expiration timestamp if it's present in the payload
            $updateData = [
                'whatsapp_consumer_phone_number_id' => $consumerPhoneNumber->id,
            ];

            // Check if 'expiration_timestamp' exists in the API payload
            if (isset($status['conversation']['expiration_timestamp'])) {
                $updateData['expiration_timestamp'] = $status['conversation']['expiration_timestamp'];
            }

            // Update the conversation with the new data
            $conversation->update($updateData);
        }

        // Update the message with the conversation ID
        $whatsappMessage->update(['whatsapp_conversation_id' => $conversationID]);

        ///// use wallet
        $this->WhatsappWallet($status['conversation']['origin']['type'] ?? null, $conversationID, $phoneNumberID, $whatsappMessage->conversation_id);
    }

    private function logMetaConversation(string $decision, WhatsappMessage $message, ?array $status = null, bool $wasBlocked = false): void
    {
        MetaConversationLog::create([
            'conversation_id' => $message->conversation_id,
            'whatsapp_message_id' => $message->id,
            'whatsapp_conversation_id' => $message->whatsapp_conversation_id,
            'decision' => $decision,
            'category_attempted' => $status['pricing']['category'] ?? optional($message->template?->whatsappTemplate)?->category,
            'message_type' => $message->type,
            'direction' => $message->direction,
            'was_blocked' => $wasBlocked,
            'meta_error_code' => $status['errors'][0]['code'] ?? null,
            'meta_error_message' => $status['errors'][0]['message'] ?? null,
            'text_log' => MetaConversationTextLogs::get($decision),
        ]);
    }

    private function mapContacts(array $contacts): array
    {
        $contactsMap = [];
        foreach ($contacts as $contact) {
            if (isset($contact['wa_id'], $contact['profile']['name'])) {
                $contactsMap[$contact['wa_id']] = $contact['profile']['name'];
            }
        }
        return $contactsMap;
    }

    private function handleTextMessages(array $messages, array $contactsMap, string $whatsappBusinessAccountID, string $phoneNumberID): void
    {
        foreach ($messages as $message) {
            // Ensure the message is of type text
            if (empty($message['text']['body'])) {
                Log::info("Message is not of type text. Skipping...");
                continue;
            }

            // Extract necessary message details
            $whatsappMessageID = $message['id'];
            $fromPhoneNumberId = $message['from'];
            $body = $message['text']['body'];
            $timestamp = $message['timestamp'];
            $previewUrl = $message['preview_url'] ?? null;
            $contactName = $contactsMap[$fromPhoneNumberId] ?? null;

            // Extract context information (if this message is a reply to another message)
            $context = $message['context'] ?? null;
            $repliedToMessageId = $context['id'] ?? null;
            $repliedToMessageFrom = $context['from'] ?? null;

            // Find or create sender
            $sender = WhatsappConsumerPhoneNumber::firstOrCreate(
                [
                    'phone_number' => $this->normalizePhoneNumber($fromPhoneNumberId),
                    'whatsapp_business_account_id' => $whatsappBusinessAccountID,
                ],
                [
                    'wa_id' => $fromPhoneNumberId,
                    'name' => $contactName,
                ]
            );

            $whatsappMessage = WhatsappMessage::whereId($whatsappMessageID)->first();

            if (WhatsappMessage::whereId($whatsappMessageID)->doesntExist()) {


                // Create the WhatsApp message
                $whatsappMessage = WhatsappMessage::create([
                    'id' => $whatsappMessageID,
                    'whatsapp_phone_number_id' => $phoneNumberID,
                    'sender_type' => get_class($sender),
                    'sender_id' => $sender->id,
                    'recipient_id' => $phoneNumberID,
                    'recipient_type' => WhatsappPhoneNumber::class,
                    'sender_role' => WhatsappMessage::MESSAGE_SENDER_ROLE_CONSUMER,
                    'type' => WhatsappMessage::MESSAGE_TYPE_TEXT,
                    'direction' => WhatsappMessage::MESSAGE_DIRECTION_RECEIVED,
                    'status' => WhatsappMessage::MESSAGE_STATUS_DELIVERED,
                    'replied_to_message_id' => $repliedToMessageId,
                    'replied_to_message_from' => $repliedToMessageFrom,
                ]);

                // Store or update the status
                WhatsappMessageStatus::updateOrCreate(
                    [
                        'whatsapp_message_id' => $whatsappMessage->id,
                        'status' => WhatsappMessage::MESSAGE_STATUS_DELIVERED,
                    ],
                    [
                        'timestamp' => $timestamp
                    ]
                );

                // Create the text message
                $whatsappTextMessage = WhatsappTextMessage::create([
                    'whatsapp_message_id' => $whatsappMessage->id,
                    'body' => $body,
                    'preview_url' => $previewUrl,
                ]);

                // Update the messageable relation in the WhatsappMessage
                $didUpdate = $whatsappMessage->update([
                    'messageable_id' => $whatsappTextMessage->id,
                    'messageable_type' => WhatsappTextMessage::class,
                ]);

                $conversation = $this->startConversationFromWhatsappMessage(
                    $whatsappMessage,
                    $whatsappBusinessAccountID,
                    $fromPhoneNumberId,
                    $contactsMap[$fromPhoneNumberId] ?? null
                );
                
                if ($conversation) {
                    // Process auto-translation for the incoming message
                   $this->processAutoTranslation($whatsappMessage, $conversation, $body);

                    event(new UnifiedMessageEvent(
                        Channel::WHATSAPP_PLATFORM,
                        'new-message',
                        [
                            'message_id' => $whatsappMessage->id,
                            'conversation_id' => $conversation->id,
                            'conversation' => [
                                'data' => new \App\Http\Responses\Conversation($conversation),
                            ],
                            'message' => [
                                'data' => new ConversationMessage($whatsappMessage, Channel::WHATSAPP_PLATFORM),
                            ],
                        ],
                        $conversation->channel_id,
                        null,
                        $conversation->workspace_id
                    ));
                }
            }
        }
    }

    private function handleImageMessages(array $message, array $contactsMap, string $whatsappBusinessAccountID, string $phoneNumberID): void
    {
        // Extract necessary message details
        $whatsappMessageID = $message['id'];
        $fromPhoneNumberId = $message['from'];
        $timestamp = $message['timestamp'];
        $imageDetails = $message['image'];
        $caption = $imageDetails['caption'] ?? null;
        $mediaId = $imageDetails['id'];
        $contactName = $contactsMap[$fromPhoneNumberId] ?? null;

        // Extract context information (if this message is a reply to another message)
        $context = $message['context'] ?? null;
        $repliedToMessageId = $context['id'] ?? null;
        $repliedToMessageFrom = $context['from'] ?? null;

        // Find or create sender
        $sender = WhatsappConsumerPhoneNumber::firstOrCreate(
            [
                'phone_number' => $this->normalizePhoneNumber($fromPhoneNumberId),
                'whatsapp_business_account_id' => $whatsappBusinessAccountID,
            ],
            [
                'wa_id' => $fromPhoneNumberId,
                'name' => $contactName,
            ]
        );

        $whatsappMessage = WhatsappMessage::whereId($whatsappMessageID)->first();

        // Check if the message already exists
        if (WhatsappMessage::whereId($whatsappMessageID)->doesntExist()) {
            // Create the WhatsApp message
            $whatsappMessage = WhatsappMessage::create([
                'id' => $whatsappMessageID,
                'whatsapp_phone_number_id' => $phoneNumberID,
                'sender_type' => get_class($sender),
                'sender_id' => $sender->id,
                'recipient_id' => $phoneNumberID,
                'recipient_type' => WhatsappPhoneNumber::class,
                'sender_role' => WhatsappMessage::MESSAGE_SENDER_ROLE_CONSUMER,
                'type' => WhatsappMessage::MESSAGE_TYPE_IMAGE,
                'direction' => WhatsappMessage::MESSAGE_DIRECTION_RECEIVED,
                'status' => WhatsappMessage::MESSAGE_STATUS_DELIVERED,
                'replied_to_message_id' => $repliedToMessageId,
                'replied_to_message_from' => $repliedToMessageFrom,
            ]);


            // Create the image message
            $whatsappImageMessage = WhatsappImageMessage::create([
                'whatsapp_message_id' => $whatsappMessage->id,
                'media_id' => $mediaId,
                'caption' => $caption,
            ]);


            // Update the messageable relation in the WhatsappMessage
            $whatsappMessage->update([
                'messageable_id' => $whatsappImageMessage->id,
                'messageable_type' => WhatsappImageMessage::class,
            ]);

            // Step 1: Download the image from WhatsApp Cloud API using the media ID
            $media = $this->downloadMediaFromWhatsAppCloudAPIV2($mediaId);
            if ($media) {
                $imageUrl = $media['url'];
                $imageMimeType = $media['mime_type'];
                $imageFileSize = $media['file_size'];
                $imageId = $media['id'];

            }
            if ($imageUrl) {
                // Step 2: Add the image to Spatie Media Library
                $accessToken = Meta::ACCESS_TOKEN;
                $imageContent = Http::withToken($accessToken)->get($imageUrl)->body(); // Fetch the image content

                // Generate a proper filename using mime type and media ID
                $fileExtension = $this->getFileExtensionFromMimeType($imageMimeType);
                $fileName = "whatsapp_image_{$mediaId}.{$fileExtension}";

                // Add the image to Spatie Media Library
                $whatsappImageMessage->addMediaFromStream($imageContent)  // Directly use the image stream
                    ->usingFileName($fileName) // Use generated filename with proper extension
                    ->toMediaCollection('whatsapp-images', 'oss'); // Store in Alibaba OSS
            }

            // Store or update the status
            WhatsappMessageStatus::updateOrCreate(
                [
                    'whatsapp_message_id' => $whatsappMessage->id,
                    'status' => WhatsappMessage::MESSAGE_STATUS_DELIVERED,
                ],
                [
                    'timestamp' => $timestamp
                ]
            );

            $conversation = $this->startConversationFromWhatsappMessage(
                $whatsappMessage,
                $whatsappBusinessAccountID,
                $fromPhoneNumberId,
                $contactsMap[$fromPhoneNumberId] ?? null
            );

            if ($conversation) {
                // Process auto-translation for image caption
                if (!empty($caption)) {
                   $this->processAutoTranslation($whatsappMessage, $conversation, $caption);
                }

                event(new UnifiedMessageEvent(
                    Channel::WHATSAPP_PLATFORM,
                    'new-message',
                    [
                        'message_id' => $whatsappMessage->id,
                        'conversation_id' => $conversation->id,
                        'conversation' => [
                            'data' => new \App\Http\Responses\Conversation($conversation),
                        ],
                        'message' => [
                            'data' => new ConversationMessage($whatsappMessage, Channel::WHATSAPP_PLATFORM),
                        ],
                    ],
                    $conversation->channel_id,
                    null,
                    $conversation->workspace_id
                ));
            }
        }
    }

    private function handleDocumentMessages(array $message, array $contactsMap, string $whatsappBusinessAccountID, string $phoneNumberID): void
    {
        // Extract necessary message details
        $whatsappMessageID = $message['id'];
        $fromPhoneNumberId = $message['from'];
        $timestamp = $message['timestamp'];
        $documentDetails = $message['document'];
        $documentUrl = $documentDetails['url'];
        $documentFileName = $documentDetails['filename'] ?? null;
        $caption = $documentDetails['caption'] ?? null;
        $mediaId = $documentDetails['id'];
        $contactName = $contactsMap[$fromPhoneNumberId] ?? null;

        // Extract context information (if this message is a reply to another message)
        $context = $message['context'] ?? null;
        $repliedToMessageId = $context['id'] ?? null;
        $repliedToMessageFrom = $context['from'] ?? null;

        // Find or create sender
        $sender = WhatsappConsumerPhoneNumber::firstOrCreate(
            [
                'phone_number' => $this->normalizePhoneNumber($fromPhoneNumberId),
                'whatsapp_business_account_id' => $whatsappBusinessAccountID,
            ],
            [
                'wa_id' => $fromPhoneNumberId,
                'name' => $contactName,
            ]
        );

        $whatsappMessage = WhatsappMessage::whereId($whatsappMessageID)->first();

        // Check if the message already exists
        if (WhatsappMessage::whereId($whatsappMessageID)->doesntExist()) {
            // Create the WhatsApp message
            $whatsappMessage = WhatsappMessage::create([
                'id' => $whatsappMessageID,
                'whatsapp_phone_number_id' => $phoneNumberID,
                'sender_type' => get_class($sender),
                'sender_id' => $sender->id,
                'recipient_id' => $phoneNumberID,
                'recipient_type' => WhatsappPhoneNumber::class,
                'sender_role' => WhatsappMessage::MESSAGE_SENDER_ROLE_CONSUMER,
                'type' => WhatsappMessage::MESSAGE_TYPE_DOCUMENT,
                'direction' => WhatsappMessage::MESSAGE_DIRECTION_RECEIVED,
                'status' => WhatsappMessage::MESSAGE_STATUS_DELIVERED,
                'replied_to_message_id' => $repliedToMessageId,
                'replied_to_message_from' => $repliedToMessageFrom,
            ]);


            // Create the document message
            $whatsappDocumentMessage = WhatsappDocumentMessage::create([
                'whatsapp_message_id' => $whatsappMessage->id,
                'media_id' => $mediaId,
                'link' => $documentUrl,
                'filename' => $documentFileName,
                'caption' => $caption,
            ]);


            // Update the messageable relation in the WhatsappMessage
            $whatsappMessage->update([
                'messageable_id' => $whatsappDocumentMessage->id,
                'messageable_type' => WhatsappDocumentMessage::class,
            ]);

            // Step 1: Download the document from WhatsApp Cloud API using the media ID
            $media = $this->downloadMediaFromWhatsAppCloudAPIV2($mediaId);
            if ($media) {
                $documentUrl = $media['url'];
                $documentMimeType = $media['mime_type'];
                $documentFileSize = $media['file_size'];
                $documentId = $media['id'];

            }
            if ($documentUrl) {
                // Step 2: Add the document to Spatie Media Library
                $accessToken = Meta::ACCESS_TOKEN;
                $documentContent = Http::withToken($accessToken)->get($documentUrl)->body(); // Fetch the document content

                // Generate a proper filename using mime type and media ID
                $fileExtension = $this->getFileExtensionFromMimeType($documentMimeType);
                $fileName = "whatsapp_document_{$mediaId}.{$fileExtension}";
                // Add the document to Spatie Media Library
                $whatsappDocumentMessage->addMediaFromStream($documentContent)  // Directly use the image stream
                    ->usingFileName($fileName) // Use generated filename with proper extension
                    ->toMediaCollection('whatsapp-documents', 'oss'); // Store in Alibaba OSS
            }

            // Store or update the status
            WhatsappMessageStatus::updateOrCreate(
                [
                    'whatsapp_message_id' => $whatsappMessage->id,
                    'status' => WhatsappMessage::MESSAGE_STATUS_DELIVERED,
                ],
                [
                    'timestamp' => $timestamp
                ]
            );

            $conversation = $this->startConversationFromWhatsappMessage(
                $whatsappMessage,
                $whatsappBusinessAccountID,
                $fromPhoneNumberId,
                $contactsMap[$fromPhoneNumberId] ?? null
            );

            if ($conversation) {
                // Process auto-translation for document caption
                if (!empty($caption)) {
                   $this->processAutoTranslation($whatsappMessage, $conversation, $caption);
                }

                event(new UnifiedMessageEvent(
                    Channel::WHATSAPP_PLATFORM,
                    'new-message',
                    [
                        'message_id' => $whatsappMessage->id,
                        'conversation_id' => $conversation->id,
                        'conversation' => [
                            'data' => new \App\Http\Responses\Conversation($conversation),
                        ],
                        'message' => [
                            'data' => new ConversationMessage($whatsappMessage, Channel::WHATSAPP_PLATFORM),
                        ],
                    ],
                    $conversation->channel_id,
                    null,
                    $conversation->workspace_id
                ));
            }
        }
    }

    private function handleVideoMessages(array $message, array $contactsMap, string $whatsappBusinessAccountID, string $phoneNumberID): void
    {
        // Extract necessary message details
        $whatsappMessageID = $message['id'];
        $fromPhoneNumberId = $message['from'];
        $timestamp = $message['timestamp'];
        $videoDetails = $message['video'];
        $caption = $videoDetails['caption'] ?? null;
        $mediaId = $videoDetails['id'];
        $contactName = $contactsMap[$fromPhoneNumberId] ?? null;

        // Extract context information (if this message is a reply to another message)
        $context = $message['context'] ?? null;
        $repliedToMessageId = $context['id'] ?? null;
        $repliedToMessageFrom = $context['from'] ?? null;

        // Find or create sender
        $sender = WhatsappConsumerPhoneNumber::firstOrCreate(
            [
                'phone_number' => $this->normalizePhoneNumber($fromPhoneNumberId),
                'whatsapp_business_account_id' => $whatsappBusinessAccountID,
            ],
            [
                'wa_id' => $fromPhoneNumberId,
                'name' => $contactName,
            ]
        );

        $whatsappMessage = WhatsappMessage::whereId($whatsappMessageID)->first();

        // Check if the message already exists
        if (WhatsappMessage::whereId($whatsappMessageID)->doesntExist()) {
            // Create the WhatsApp message
            $whatsappMessage = WhatsappMessage::create([
                'id' => $whatsappMessageID,
                'whatsapp_phone_number_id' => $phoneNumberID,
                'sender_type' => get_class($sender),
                'sender_id' => $sender->id,
                'recipient_id' => $phoneNumberID,
                'recipient_type' => WhatsappPhoneNumber::class,
                'sender_role' => WhatsappMessage::MESSAGE_SENDER_ROLE_CONSUMER,
                'type' => WhatsappMessage::MESSAGE_TYPE_VIDEO,
                'direction' => WhatsappMessage::MESSAGE_DIRECTION_RECEIVED,
                'status' => WhatsappMessage::MESSAGE_STATUS_DELIVERED,
                'replied_to_message_id' => $repliedToMessageId,
                'replied_to_message_from' => $repliedToMessageFrom,
            ]);


            // Create the video message
            $whatsappVideoMessage = WhatsappVideoMessage::create([
                'whatsapp_message_id' => $whatsappMessage->id,
                'media_id' => $mediaId,
                'caption' => $caption,
            ]);


            // Update the messageable relation in the WhatsappMessage
            $whatsappMessage->update([
                'messageable_id' => $whatsappVideoMessage->id,
                'messageable_type' => WhatsappVideoMessage::class,
            ]);

            // Step 1: Download the Video from WhatsApp Cloud API using the media ID
            $media = $this->downloadMediaFromWhatsAppCloudAPIV2($mediaId);
            if ($media) {
                $videoUrl = $media['url'];
                $videoMimeType = $media['mime_type'];
                $videoFileSize = $media['file_size'];
                $videoId = $media['id'];
                // Add the Video to Spatie Media Library

            }

            if ($videoUrl) {
                // Step 2: Add the Video to Spatie Media Library
                $accessToken = Meta::ACCESS_TOKEN;
                $videoContent = Http::withToken($accessToken)->get($videoUrl)->body();
                // Fetch the Video content
                $fileExtension = $this->getFileExtensionFromMimeType($videoMimeType);
                $fileName = "whatsapp_viedo_{$mediaId}.{$fileExtension}";

                $whatsappVideoMessage->addMediaFromStream($videoContent)  // Directly use the Video stream
                    ->usingFileName($fileName)
                    ->toMediaCollection('whatsapp-videos', 'oss'); // Store in Alibaba OSS
            }


            // Store or update the status
            WhatsappMessageStatus::updateOrCreate(
                [
                    'whatsapp_message_id' => $whatsappMessage->id,
                    'status' => WhatsappMessage::MESSAGE_STATUS_DELIVERED,
                ],
                [
                    'timestamp' => $timestamp
                ]
            );

            $conversation = $this->startConversationFromWhatsappMessage(
                $whatsappMessage,
                $whatsappBusinessAccountID,
                $fromPhoneNumberId,
                $contactsMap[$fromPhoneNumberId] ?? null
            );

            if ($conversation) {
                // Process auto-translation for video caption
                if (!empty($caption)) {
                   $this->processAutoTranslation($whatsappMessage, $conversation, $caption);
                }

                event(new UnifiedMessageEvent(
                    Channel::WHATSAPP_PLATFORM,
                    'new-message',
                    [
                        'message_id' => $whatsappMessage->id,
                        'conversation_id' => $conversation->id,
                        'conversation' => [
                            'data' => new \App\Http\Responses\Conversation($conversation),
                        ],
                        'message' => [
                            'data' => new ConversationMessage($whatsappMessage, Channel::WHATSAPP_PLATFORM),
                        ],
                    ],
                    $conversation->channel_id,
                    null,
                    $conversation->workspace_id
                ));
            }
        }
    }

    private function handleAudioMessages(array $message, array $contactsMap, string $whatsappBusinessAccountID, string $phoneNumberID): void
    {
        // Extract necessary message details
        $whatsappMessageID = $message['id'];
        $fromPhoneNumberId = $message['from'];
        $timestamp = $message['timestamp'];
        $audioDetails = $message['audio'];
        $mediaId = $audioDetails['id'];
        $contactName = $contactsMap[$fromPhoneNumberId] ?? null;

        // Extract context information (if this message is a reply to another message)
        $context = $message['context'] ?? null;
        $repliedToMessageId = $context['id'] ?? null;
        $repliedToMessageFrom = $context['from'] ?? null;

        // Find or create sender
        $sender = WhatsappConsumerPhoneNumber::firstOrCreate(
            [
                'phone_number' => $this->normalizePhoneNumber($fromPhoneNumberId),
                'whatsapp_business_account_id' => $whatsappBusinessAccountID,
            ],
            [
                'wa_id' => $fromPhoneNumberId,
                'name' => $contactName,
            ]
        );

        $whatsappMessage = WhatsappMessage::whereId($whatsappMessageID)->first();

        // Check if the message already exists
        if (WhatsappMessage::whereId($whatsappMessageID)->doesntExist()) {
            // Create the WhatsApp message
            $whatsappMessage = WhatsappMessage::create([
                'id' => $whatsappMessageID,
                'whatsapp_phone_number_id' => $phoneNumberID,
                'sender_type' => get_class($sender),
                'sender_id' => $sender->id,
                'recipient_id' => $phoneNumberID,
                'recipient_type' => WhatsappPhoneNumber::class,
                'sender_role' => WhatsappMessage::MESSAGE_SENDER_ROLE_CONSUMER,
                'type' => WhatsappMessage::MESSAGE_TYPE_AUDIO,
                'direction' => WhatsappMessage::MESSAGE_DIRECTION_RECEIVED,
                'status' => WhatsappMessage::MESSAGE_STATUS_DELIVERED,
                'replied_to_message_id' => $repliedToMessageId,
                'replied_to_message_from' => $repliedToMessageFrom,
            ]);


            // Create the audio message
            $whatsappAudioMessage = WhatsappAudioMessage::create([
                'whatsapp_message_id' => $whatsappMessage->id,
                'media_id' => $mediaId
            ]);

            // Update the messageable relation in the WhatsappMessage
            $whatsappMessage->update([
                'messageable_id' => $whatsappAudioMessage->id,
                'messageable_type' => WhatsappAudioMessage::class,
            ]);

            // Step 1: Download the audio from WhatsApp Cloud API using the media ID
            $media = $this->downloadMediaFromWhatsAppCloudAPIV2($mediaId);
            if ($media) {
                $audioUrl = $media['url'];
                $audioMimeType = $media['mime_type'];
                $audioFileSize = $media['file_size'];
                $audioId = $media['id'];

            }
            if ($audioUrl) {
                // Step 2: Add the audio to Spatie Media Library
                $accessToken = Meta::ACCESS_TOKEN;
                $audioContent = Http::withToken($accessToken)->get($audioUrl)->body(); // Fetch the audio content

                $fileExtension = $this->getFileExtensionFromMimeType($audioMimeType);
                $fileName = "whatsapp_audio_{$mediaId}.{$fileExtension}";

                // Add the audio to Spatie Media Library
                $whatsappAudioMessage->addMediaFromStream($audioContent)  // Directly use the audio stream
                    ->usingFileName($fileName) // Use generated filename with proper extension
                    ->toMediaCollection('whatsapp-audios', 'oss'); // Store in Alibaba OSS
            }


            // Store or update the status
            WhatsappMessageStatus::updateOrCreate(
                [
                    'whatsapp_message_id' => $whatsappMessage->id,
                    'status' => WhatsappMessage::MESSAGE_STATUS_DELIVERED,
                ],
                [
                    'timestamp' => $timestamp
                ]
            );

            $conversation = $this->startConversationFromWhatsappMessage(
                $whatsappMessage,
                $whatsappBusinessAccountID,
                $fromPhoneNumberId,
                $contactsMap[$fromPhoneNumberId] ?? null
            );

            if ($conversation) {
                event(new UnifiedMessageEvent(
                    Channel::WHATSAPP_PLATFORM,
                    'new-message',
                    [
                        'message_id' => $whatsappMessage->id,
                        'conversation_id' => $conversation->id,
                        'conversation' => [
                            'data' => new \App\Http\Responses\Conversation($conversation),
                        ],
                        'message' => [
                            'data' => new ConversationMessage($whatsappMessage, Channel::WHATSAPP_PLATFORM),
                        ],
                    ],
                    $conversation->channel_id,
                    null,
                    $conversation->workspace_id
                ));
            }
        }

        $this->startConversationFromWhatsappMessage(
            $whatsappMessage,
            $whatsappBusinessAccountID,
            $fromPhoneNumberId,
            $contactsMap[$fromPhoneNumberId] ?? null
        );
    }

    private function handleInteractiveMessages(array $message, array $contactsMap, string $wabaID, string $phoneNumberID): void
    {
        $whatsappMessageID = $message['id'];
        $interactiveType = $message['interactive']['type'] ?? null;
        // Handle different interactive types
        switch ($interactiveType) {
            case 'button_reply':
                $this->handleButtonReplyMessage($message, $contactsMap, $wabaID, $phoneNumberID);
                break;
            case 'list_reply':
                $this->handleListReplyMessage($message, $contactsMap, $wabaID, $phoneNumberID);
                break;
            case 'nfm_reply':
                $this->handleNfmReplyMessage($message, $contactsMap, $wabaID, $phoneNumberID);
                break;
            default:
                Log::info('Unhandled interactive type: ' . ($interactiveType ?? 'unknown'));
                break;
        }

        $whatsappMessage = WhatsappMessage::whereId($whatsappMessageID)->first();
        // $this->startConversationFromWhatsappMessage(
        //     $whatsappMessage,
        //     $wabaID,
        //     $phoneNumberID
        // );

    }

    /**
     * Handle button reply interactive messages.
     */
    private function handleButtonReplyMessage(array $message, array $contactsMap, string $wabaID, string $phoneNumberID): void
    {
        $buttonReply = $message['interactive']['button_reply'] ?? [];
        $replyId = $buttonReply['id'] ?? null;
        $replyTitle = $buttonReply['title'] ?? null;


        $whatsappMessage = $this->createInteractiveReplyMessage(
            $message,
            $contactsMap,
            $wabaID,
            $phoneNumberID,
            WhatsappInteractiveMessage::TYPE_BUTTON_REPLY,
            $replyId,
            $replyTitle
        );
        if ($whatsappMessage) {
            $this->dispatchInteractiveWorkflowEvent($whatsappMessage, 'button_reply', $replyId, $replyTitle);
        }

    }

    /**
     * Handle list reply interactive messages.
     */
    private function handleListReplyMessage(array $message, array $contactsMap, string $wabaID, string $phoneNumberID): void
    {
        $listReply = $message['interactive']['list_reply'] ?? [];
        $replyId = $listReply['id'] ?? null;
        $replyTitle = $listReply['title'] ?? null;
        $replyDescription = $listReply['description'] ?? null;

        $whatsappMessage = $this->createInteractiveReplyMessage(
            $message,
            $contactsMap,
            $wabaID,
            $phoneNumberID,
            WhatsappInteractiveMessage::TYPE_LIST_REPLY,
            $replyId,
            $replyTitle,
            $replyDescription
        );

        if ($whatsappMessage) {
            $this->dispatchInteractiveWorkflowEvent($whatsappMessage, 'list_reply', $replyId, $replyTitle);
        }
    }

    /**
     * Create an interactive reply message record.
     */
    private function createInteractiveReplyMessage(
        array $message,
        array $contactsMap,
        string $wabaID,
        string $phoneNumberID,
        string $interactiveType,
        ?string $replyId,
        ?string $replyTitle,
        ?string $replyDescription = null
    ): ?WhatsappMessage {
        $whatsappMessageID = $message['id'];


        $fromWaID = $message['from'];
        $timestamp = $message['timestamp'];
        $contactName = $contactsMap[$fromWaID] ?? null;
        // Extract context information (the original message this is a reply to)
        $context = $message['context'] ?? null;
        $repliedToMessageId = $context['id'] ?? null;
        $repliedToMessageFrom = $context['from'] ?? null;

        $sender = WhatsappConsumerPhoneNumber::firstOrCreate(
            [
                'phone_number' => $this->normalizePhoneNumber($fromWaID),
                'whatsapp_business_account_id' => $wabaID,
            ],
            [
                'wa_id' => $fromWaID,
                'name' => $contactName,
            ]
        );

        // Avoid duplicate processing
        if (WhatsappMessage::whereId($whatsappMessageID)->exists()) {
            return null;
        }

        $whatsappMessage = WhatsappMessage::create([
            'id' => $whatsappMessageID,
            'whatsapp_phone_number_id' => $phoneNumberID,
            'sender_type' => get_class($sender),
            'sender_id' => $sender->id,
            'recipient_id' => $phoneNumberID,
            'recipient_type' => WhatsappPhoneNumber::class,
            'sender_role' => WhatsappMessage::MESSAGE_SENDER_ROLE_CONSUMER,
            'type' => WhatsappMessage::MESSAGE_TYPE_INTERACTIVE,
            'direction' => WhatsappMessage::MESSAGE_DIRECTION_RECEIVED,
            'status' => WhatsappMessage::MESSAGE_STATUS_DELIVERED,
            'replied_to_message_id' => $repliedToMessageId,
            'replied_to_message_from' => $repliedToMessageFrom,
        ]);

        WhatsappMessageStatus::updateOrCreate(
            ['whatsapp_message_id' => $whatsappMessage->id, 'status' => WhatsappMessage::MESSAGE_STATUS_DELIVERED],
            ['timestamp' => $timestamp]
        );

        // Create the interactive message record
        $interactiveMessage = WhatsappInteractiveMessage::create([
            'whatsapp_message_id' => $whatsappMessageID,
            'interactive_type' => $interactiveType,
            'button_reply_id' => $interactiveType === WhatsappInteractiveMessage::TYPE_BUTTON_REPLY ? $replyId : null,
            'button_reply_title' => $interactiveType === WhatsappInteractiveMessage::TYPE_BUTTON_REPLY ? $replyTitle : null,
            'list_reply_id' => $interactiveType === WhatsappInteractiveMessage::TYPE_LIST_REPLY ? $replyId : null,
            'list_reply_title' => $interactiveType === WhatsappInteractiveMessage::TYPE_LIST_REPLY ? $replyTitle : null,
            'list_reply_description' => $replyDescription,
            'payload' => $message['interactive'] ?? null,
        ]);

        // Update the messageable relation
        $whatsappMessage->update([
            'messageable_id' => $interactiveMessage->id,
            'messageable_type' => WhatsappInteractiveMessage::class,
        ]);

        // Start conversation and broadcast
        $conversation = $this->startConversationFromWhatsappMessage($whatsappMessage, $wabaID, $fromWaID,$contactName);

        if ($conversation) {
            event(new UnifiedMessageEvent(
                Channel::WHATSAPP_PLATFORM,
                'new-message',
                [
                    'message_id' => $whatsappMessage->id,
                    'conversation_id' => $conversation->id,
                    'conversation' => [
                        'data' => new \App\Http\Responses\Conversation($conversation),
                    ],
                    'message' => [
                        'data' => new ConversationMessage($whatsappMessage, Channel::WHATSAPP_PLATFORM),
                    ],
                ],
                $conversation->channel_id,
                null,
                $conversation->workspace_id
            ));
        }

        return $whatsappMessage;
    }

    /**
     * Dispatch the interactive workflow event.
     */
    private function dispatchInteractiveWorkflowEvent(
        WhatsappMessage $responseMessage,
        string $replyType,
        ?string $replyId,
        ?string $replyTitle
    ): void {
        // Get the original message this is a reply to
        $originalMessageId = $responseMessage->replied_to_message_id;
        if (!$originalMessageId) {
            Log::info('No original message ID found for interactive reply', [
                'response_message_id' => $responseMessage->id,
            ]);
            return;
        }

        // Find the original WhatsappInteractiveMessage to get the draft ID
        $originalInteractiveMessage = WhatsappInteractiveMessage::where('whatsapp_message_id', $originalMessageId)->first();
        if (!$originalInteractiveMessage) {
            Log::info('Original interactive message not found', [
                'response_message_id' => $responseMessage->id,
                'original_message_id' => $originalMessageId,
            ]);
            return;
        }

        $draftId = $originalInteractiveMessage->interactive_message_draft_id;
        if (!$draftId) {
            Log::info('No draft ID found for original interactive message', [
                'response_message_id' => $responseMessage->id,
                'original_interactive_message_id' => $originalInteractiveMessage->id,
            ]);
            return;
        }

        // Dispatch the event
        event(new \App\Events\WhatsappInteractiveResponseReceived(
            $responseMessage,
            $draftId,
            $replyType,
            $replyId ?? '',
            $replyTitle
        ));

        Log::info('Dispatched interactive workflow event', [
            'response_message_id' => $responseMessage->id,
            'draft_id' => $draftId,
            'reply_type' => $replyType,
            'reply_id' => $replyId,
        ]);
    }

    /**
     * Handle NFM (WhatsApp Flows) reply messages.
     */
    private function handleNfmReplyMessage(array $message, array $contactsMap, string $wabaID, string $phoneNumberID): void
    {
        $nfmReply = $message['interactive']['nfm_reply'] ?? [];
        $responseJson = json_decode($nfmReply['response_json'] ?? '{}', true);
        $flowToken = $responseJson['flow_token'] ?? null;

        $whatsappMessageID = $message['id'];
        $fromWaID = $message['from'];
        $timestamp = $message['timestamp'];
        $contactName = $contactsMap[$fromWaID] ?? null;

        // Extract context information (if this message is a reply to another message)
        $context = $message['context'] ?? null;
        $repliedToMessageId = $context['id'] ?? null;
        $repliedToMessageFrom = $context['from'] ?? null;

        $sender = WhatsappConsumerPhoneNumber::firstOrCreate(
            [
                'phone_number' => $this->normalizePhoneNumber($fromWaID),
                'whatsapp_business_account_id' => $wabaID,
            ],
            [
                'wa_id' => $fromWaID,
                'name' => $contactName,
            ]
        );

        // Avoid duplicate processing
        if (WhatsappMessage::whereId($whatsappMessageID)->doesntExist()) {
            $whatsappMessage = WhatsappMessage::create([
                'id' => $whatsappMessageID,
                'whatsapp_phone_number_id' => $phoneNumberID,
                'sender_type' => get_class($sender),
                'sender_id' => $sender->id,
                'recipient_id' => $phoneNumberID,
                'recipient_type' => WhatsappPhoneNumber::class,
                'sender_role' => WhatsappMessage::MESSAGE_SENDER_ROLE_CONSUMER,
                'type' => WhatsappMessage::MESSAGE_TYPE_INTERACTIVE,
                'direction' => WhatsappMessage::MESSAGE_DIRECTION_RECEIVED,
                'status' => WhatsappMessage::MESSAGE_STATUS_DELIVERED,
                'replied_to_message_id' => $repliedToMessageId,
                'replied_to_message_from' => $repliedToMessageFrom,
            ]);

            WhatsappMessageStatus::updateOrCreate(
                ['whatsapp_message_id' => $whatsappMessage->id, 'status' => WhatsappMessage::MESSAGE_STATUS_DELIVERED],
                ['timestamp' => $timestamp]
            );

            $whatsappFlowResponseMessage = WhatsappFlowResponseMessage::create([
                'whatsapp_message_id' => $whatsappMessageID,
                'flow_token' => $flowToken,
                'name' => $nfmReply['name'] ?? null,
                'body' => $nfmReply['body'] ?? null,
                'response_json' => $nfmReply['response_json'] ?? null,
            ]);

            // Update the messageable relation in the WhatsappMessage
            $whatsappMessage->update([
                'messageable_id' => $whatsappFlowResponseMessage->id,
                'messageable_type' => WhatsappFlowResponseMessage::class,
            ]);

            $conversation = $this->startConversationFromWhatsappMessage($whatsappMessage, $wabaID, $fromWaID,$contactName);

            if ($conversation) {
                event(new UnifiedMessageEvent(
                    Channel::WHATSAPP_PLATFORM,
                    'new-message',
                    [
                        'message_id' => $whatsappMessage->id,
                        'conversation_id' => $conversation->id,
                        'conversation' => [
                            'data' => new \App\Http\Responses\Conversation($conversation),
                        ],
                        'message' => [
                            'data' => new ConversationMessage($whatsappMessage, Channel::WHATSAPP_PLATFORM),
                        ],
                    ],
                    $conversation->channel_id,
                    null,
                    $conversation->workspace_id
                ));
            }
        }
    }

    private function handleButtonMessages(array $message, array $contactsMap, string $whatsappBusinessAccountID, string $phoneNumberID): void
    {
        // dd($message);
        $whatsappMessageID = $message['id'];
        $fromPhoneNumberId = $message['from'];
        $timestamp = $message['timestamp'];
        $contactName = $contactsMap[$fromPhoneNumberId] ?? null;

        // Extract context information (if this message is a reply to another message)
        $context = $message['context'] ?? null;
        $repliedToMessageId = $context['id'] ?? null;
        $repliedToMessageFrom = $context['from'] ?? null;

        // Find or create sender
        $sender = WhatsappConsumerPhoneNumber::firstOrCreate(
            [
                'phone_number' => $this->normalizePhoneNumber($fromPhoneNumberId),
                'whatsapp_business_account_id' => $whatsappBusinessAccountID,
            ],
            [
                'wa_id' => $fromPhoneNumberId,
                'name' => $contactName,
            ]
        );

        $whatsappMessage = WhatsappMessage::whereId($whatsappMessageID)->first();
        // Check if the message already exists
        if (!$whatsappMessage) {
            // Create the WhatsApp message
            $whatsappMessage = WhatsappMessage::create([
                'id' => $whatsappMessageID,
                'whatsapp_phone_number_id' => $phoneNumberID,
                'sender_type' => get_class($sender),
                'sender_id' => $sender->id,
                'recipient_id' => $phoneNumberID,
                'recipient_type' => WhatsappPhoneNumber::class,
                'sender_role' => WhatsappMessage::MESSAGE_SENDER_ROLE_CONSUMER,
                'type' => WhatsappMessage::MESSAGE_TYPE_TEXT,
                'direction' => WhatsappMessage::MESSAGE_DIRECTION_RECEIVED,
                'status' => WhatsappMessage::MESSAGE_STATUS_DELIVERED,
                'replied_to_message_id' => $repliedToMessageId,
                'replied_to_message_from' => $repliedToMessageFrom,
            ]);


            // Create the image message
            WhatsappMessageStatus::updateOrCreate(
                [
                    'whatsapp_message_id' => $whatsappMessage->id,
                    'status' => WhatsappMessage::MESSAGE_STATUS_DELIVERED,
                ],
                [
                    'timestamp' => $timestamp
                ]
            );
            //TODO: add wahtsapp button message
            $body = $message['button']['text'] ?? null;
            // Create the text message
            $whatsappTextMessage = WhatsappTextMessage::create([
                'whatsapp_message_id' => $whatsappMessage->id,
                'body' => $body,
                'preview_url' => null,
            ]);

            // Update the messageable relation in the WhatsappMessage
            $whatsappMessage->update([
                'messageable_id' => $whatsappTextMessage->id,
                'messageable_type' => WhatsappTextMessage::class,
            ]);


            $conversation = $this->startConversationFromWhatsappMessage(
                $whatsappMessage,
                $whatsappBusinessAccountID,
                $fromPhoneNumberId,
                $contactName
            );

            if ($conversation) {
                // Process auto-translation for button text
                if (!empty($body)) {
                   $this->processAutoTranslation($whatsappMessage, $conversation, $body);
                }

                event(new UnifiedMessageEvent(
                    Channel::WHATSAPP_PLATFORM,
                    'new-message',
                    [
                        'message_id' => $whatsappMessage->id,
                        'conversation_id' => $conversation->id,
                        'conversation' => [
                            'data' => new \App\Http\Responses\Conversation($conversation),
                        ],
                        'message' => [
                            'data' => new ConversationMessage($whatsappMessage, Channel::WHATSAPP_PLATFORM),
                        ],
                    ],
                    $conversation->channel_id,
                    null,
                    $conversation->workspace_id
                ));
            }
        }
    }

    private function handleReactionMessages(array $message, array $contactsMap, string $whatsappBusinessAccountID, string $phoneNumberID): void
    {
        // Extract necessary message details
        $whatsappMessageID = $message['id'];
        $fromPhoneNumberId = $message['from'];
        $timestamp = $message['timestamp'];
        $reactionDetails = $message['reaction'];
        $messageId = $reactionDetails['message_id']; // The message being reacted to
        $emoji = $reactionDetails['emoji'] ?? null; // Empty string means reaction removed
        $contactName = $contactsMap[$fromPhoneNumberId] ?? null;

        // Extract context information (if this message is a reply to another message)
        $context = $message['context'] ?? null;
        $repliedToMessageId = $context['id'] ?? null;
        $repliedToMessageFrom = $context['from'] ?? null;

        // Find or create sender
        $sender = WhatsappConsumerPhoneNumber::firstOrCreate(
            [
                'phone_number' => $this->normalizePhoneNumber($fromPhoneNumberId),
                'whatsapp_business_account_id' => $whatsappBusinessAccountID,
            ],
            [
                'wa_id' => $fromPhoneNumberId,
                'name' => $contactName,
            ]
        );

        $whatsappMessage = WhatsappMessage::whereId($whatsappMessageID)->first();

        // Check if the message already exists
        if (WhatsappMessage::whereId($whatsappMessageID)->doesntExist()) {
            // Create the WhatsApp message
            $whatsappMessage = WhatsappMessage::create([
                'id' => $whatsappMessageID,
                'whatsapp_phone_number_id' => $phoneNumberID,
                'sender_type' => get_class($sender),
                'sender_id' => $sender->id,
                'recipient_id' => $phoneNumberID,
                'recipient_type' => WhatsappPhoneNumber::class,
                'sender_role' => WhatsappMessage::MESSAGE_SENDER_ROLE_CONSUMER,
                'type' => WhatsappMessage::MESSAGE_TYPE_REACTION,
                'direction' => WhatsappMessage::MESSAGE_DIRECTION_RECEIVED,
                'status' => WhatsappMessage::MESSAGE_STATUS_DELIVERED,
                'replied_to_message_id' => $repliedToMessageId,
                'replied_to_message_from' => $repliedToMessageFrom,
            ]);

            // Store or update the status
            WhatsappMessageStatus::updateOrCreate(
                [
                    'whatsapp_message_id' => $whatsappMessage->id,
                    'status' => WhatsappMessage::MESSAGE_STATUS_DELIVERED,
                ],
                [
                    'timestamp' => $timestamp
                ]
            );

            // Create the reaction message
            $whatsappReactionMessage = WhatsappReactionMessage::create([
                'whatsapp_message_id' => $whatsappMessage->id,
                'message_id' => $messageId,
                'emoji' => $emoji,
                'direction' => WhatsappMessage::MESSAGE_DIRECTION_RECEIVED
            ]);

            // Update the messageable relation in the WhatsappMessage
            $whatsappMessage->update([
                'messageable_id' => $whatsappReactionMessage->id,
                'messageable_type' => WhatsappReactionMessage::class,
            ]);

            $conversation = $this->startConversationFromWhatsappMessage(
                $whatsappMessage,
                $whatsappBusinessAccountID,
                $fromPhoneNumberId,
                $contactName
            );

            if ($conversation) {
                $originalMessage = $this->findOriginalMessage($messageId, $conversation->id);
                $whatsappReactionMessage->update([
                    'message_id' => $originalMessage->id ?? $messageId,
                ]);
                event(new UnifiedMessageEvent(
                    Channel::WHATSAPP_PLATFORM,
                    'new-message',
                    [
                        'message_id' => $whatsappMessage->id,
                        'conversation_id' => $conversation->id,
                        'conversation' => [
                            'data' => new \App\Http\Responses\Conversation($conversation),
                        ],
                        'message' => [
                            'data' => new ConversationMessage($whatsappMessage, Channel::WHATSAPP_PLATFORM),
                        ],
                    ],
                    $conversation->channel_id,
                    null,
                    $conversation->workspace_id
                ));
            }
        }
    }

    /**
     * Find the original message that was reacted to
     * Uses multiple strategies to handle WhatsApp's inconsistent message IDs
     */
    private function findOriginalMessage(string $messageId, string $conversationID): ?WhatsappMessage
    {
        // Strategy 1: Try exact match first
        $message = WhatsappMessage::whereId($messageId)->first();
        if ($message) {
            return $message;
        }

        // Strategy 2: Extract the key part of the message ID and search



        // // Strategy 3: Find most recent message sent to this user
        // // (within last 24 hours to avoid false matches)
        $message = WhatsappMessage::where('conversation_id', $conversationID)
            ->where('direction', WhatsappMessage::MESSAGE_DIRECTION_SENT)
            ->where('created_at', '>=', now()->subDay())
            ->orderBy('created_at', 'desc')
            ->first();
        if ($message) {
            return $message;
        }

        return null;
    }

    /**
     * Fallback method: find most recent message to user
     */
    private function findByFallback(string $fromPhoneNumberId, string $phoneNumberID): ?WhatsappMessage
    {
        $message = WhatsappMessage::where('whatsapp_phone_number_id', $phoneNumberID)
            ->where('direction', WhatsappMessage::MESSAGE_DIRECTION_SENT)
            ->whereHas('recipient', function ($query) use ($fromPhoneNumberId) {
                $query->where('phone_number', $this->normalizePhoneNumber($fromPhoneNumberId))
                    ->orWhere('wa_id', $fromPhoneNumberId);
            })
            ->where('created_at', '>=', now()->subHours(2)) // Only very recent messages
            ->orderBy('created_at', 'desc')
            ->first();

        if ($message) {
            \Log::warning('Found message by fallback (most recent)', [
                'message_id' => $message->id,
                'note' => 'This might not be 100% accurate if multiple messages were sent recently'
            ]);
        } else {
            \Log::error('Could not find original message for reaction - all strategies failed', [
                'from' => $fromPhoneNumberId,
                'phone_number_id' => $phoneNumberID
            ]);
        }

        return $message;
    }

    /**
     * Handle 'statuses' events.
     *
     * @param array $value
     * @return void
     */
    protected function handleStatusesEvent(array $value): void
    {
        // Your logic to handle status events
        Log::info("Handling status event", $value);
    }
}
