<?php

namespace App\Http\Controllers;

use App\Constants\Meta;
use App\Models\Channel;
use App\Models\MessageTranslationBilling;
use App\Models\OrganizationWhatsappExtra;
use App\Models\Service;
use App\Models\WhatsappConsumerPhoneNumber;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Models\WhatsappPhoneNumber;
use App\Traits\BusinessTokenManager;
use App\Traits\WalletManager;
use App\Traits\WhatsappMessageManager;
use App\Traits\WhatsappTemplateManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Traits\Translation;
class WhatsappChatController extends BaseApiController
{

    use BusinessTokenManager, WhatsappMessageManager, WhatsappTemplateManager, Translation, WalletManager;

    /**
     * @OA\Get(
     *     path="/api/whatsapp/chat-list",
     *     summary="Get WhatsApp chat list",
     *     tags={"WhatsApp Chat"},
     *     description="Fetches a list of WhatsApp consumer phone numbers and their latest messages, along with unread message counts.",
     *     @OA\Response(
     *         response=200,
     *         description="Chat List Fetched Successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Chat List Fetched Successfully"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="phone_number_id", type="integer", example=1),
     *                     @OA\Property(property="phone_number", type="string", example="+1234567890"),
     *                     @OA\Property(property="unread_notifications_count", type="integer", example=3),
     *                     @OA\Property(property="last_message", type="string", example="Hello there!"),
     *                     @OA\Property(property="last_message_type", type="string", example="text"),
     *                     @OA\Property(property="timestamp", type="integer", example=1727274362)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getChatList(Request $request, Channel $channel)
    {
        $connector = $channel->connector;

        // Retrieve the phone_number_id from WhatsappConfiguration
        $whatsappConfiguration = $connector->whatsappConfiguration;

        $whatsappBusinessAccount = $whatsappConfiguration->whatsappBusinessAccount;

        $whatsappPhoneNumberID = $whatsappConfiguration->primary_whatsapp_phone_number_id;


        $whatsappConsumerPhoneNumbers = WhatsappConsumerPhoneNumber::whereWhatsappBusinessAccountId($whatsappBusinessAccount->id)->get(['id', 'phone_number']);
        $chatList = [];

        $filter = $request->get('filter');

        foreach ($whatsappConsumerPhoneNumbers as $consumerPhoneNumber) {
            $id = $consumerPhoneNumber['id'];
            $phone_number = $consumerPhoneNumber['phone_number'];

            $lastMessage = WhatsappMessage::whereWhatsappPhoneNumberId($whatsappPhoneNumberID)->where(function ($query) use ($id) {
                $query->where(function ($subQuery) use ($id) {
                    $subQuery->where(function ($subSubQuery) use ($id) {
                        $subSubQuery->where('recipient_type', '=', WhatsappConsumerPhoneNumber::class)
                            ->where('recipient_id', '=', $id);
                    })
                        ->orWhere(function ($subSubQuery) use ($id) {
                            $subSubQuery->where('sender_type', '=', WhatsappConsumerPhoneNumber::class)
                                ->where('sender_id', '=', $id);
                        });
                });
            })
                ->orderBy('created_at', 'desc') // Order messages by the latest
                ->first();

            $unreadCount = WhatsappMessage::whereWhatsappPhoneNumberId($whatsappPhoneNumberID)
                ->where('sender_type', '=', WhatsappConsumerPhoneNumber::class)
                ->where('sender_id', '=', $id)
                ->whereIn(
                    'status',
                    [
                        WhatsappMessage::MESSAGE_STATUS_INITIATED,
                        WhatsappMessage::MESSAGE_STATUS_DELIVERED,
                        WhatsappMessage::MESSAGE_DIRECTION_SENT
                    ]
                )
                ->count();

            $conversation_expiration_timestamp = WhatsappConversation::where(
                'whatsapp_consumer_phone_number_id',
                '=',
                $id
            )->where('expiration_timestamp', '>', time())
                ->orderBy('expiration_timestamp', 'desc')
                ->first()?->expiration_timestamp;

            $includeInChatList = $this->applyChatFilter($lastMessage, $filter);


            if ($includeInChatList) {
                if ($lastMessage) {
                    $lastMessageContent = match ($lastMessage->type) {
                        WhatsappMessage::MESSAGE_TYPE_IMAGE => 'Sent an image',
                        WhatsappMessage::MESSAGE_TYPE_VIDEO => 'Sent a video',
                        WhatsappMessage::MESSAGE_TYPE_AUDIO => 'Sent an audio message',
                        WhatsappMessage::MESSAGE_TYPE_TEMPLATE => $this->getTemplateBodyWithParameters($lastMessage),
                        default => $lastMessage->messageable,
                    };
                }

                $chatList[] = [
                    'phone_number_id' => $id,
                    'phone_number' => $phone_number,
                    'unread_notifications_count' => $unreadCount,
                    'last_message' => $lastMessageContent ?? null,
                    'last_message_type' => $lastMessage->type,
                    'timestamp' => $lastMessage->created_at,
                    'conversation_expiration_timestamp' => $conversation_expiration_timestamp,
                ];
            }
        }

        // Sort the chat list by the last conversation timestamp
        usort($chatList, function ($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        return $this->response(true, 'Chat List Fetched Successfully', $chatList);

    }

    /**
     * Apply chat filters based on the last message and the requested filter.
     */
    private function applyChatFilter($lastMessage, $filter): bool
    {
        if (!$lastMessage) {
            return false; // No message to apply a filter on
        }

        return match ($filter) {
            'to_be_answered' => $lastMessage->direction === WhatsappMessage::MESSAGE_DIRECTION_RECEIVED
            && $lastMessage->status === WhatsappMessage::MESSAGE_STATUS_DELIVERED,

            'read' => $lastMessage->status === WhatsappMessage::MESSAGE_STATUS_READ,

            'answered' => $lastMessage->direction === WhatsappMessage::MESSAGE_DIRECTION_SENT,

            default => true,
        };
    }

    /**
     * @OA\Get(
     *     path="/api/whatsapp/chat-messages/{phone_number_id}",
     *     summary="Get all WhatsApp chat messages",
     *     tags={"WhatsApp Chat"},
     *     description="Fetches all chat messages for a specified phone number.",
     *     @OA\Parameter(
     *         name="phone_number_id",
     *         in="path",
     *         required=true,
     *         description="The ID of the phone number for which to fetch messages",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chat Messages Fetched Successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Chat Messages Fetched Successfully"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="string", example="wamid.HBgMMjAxMTI2MjIwODA2FQIAERgSQ0U3MzEyQTQyMUY0OTRGMjVEAA=="),
     *                     @OA\Property(property="whatsapp_phone_number_id", type="integer", example=108427225641466),
     *                     @OA\Property(property="sender_type", type="string", example="App\\Models\\WhatsappPhoneNumber"),
     *                     @OA\Property(property="sender_id", type="integer", example=108427225641466),
     *                     @OA\Property(property="recipient_type", type="string", example="App\\Models\\WhatsappConsumerPhoneNumber"),
     *                     @OA\Property(property="recipient_id", type="integer", example=1),
     *                     @OA\Property(property="sender_role", type="string", example="BUSINESS"),
     *                     @OA\Property(property="type", type="string", example="text"),
     *                     @OA\Property(property="direction", type="string", example="SENT"),
     *                     @OA\Property(property="status", type="string", example="initiated"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-25T14:26:02.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-25T14:26:02.000000Z")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getChatMessages2(Request $request, $phone_number_id): JsonResponse
    {
        // Fetch messages where the phone number is either the sender or the recipient
        $whatsapp_message_id = $request->input('id', null);
        $lang = $request->input('lang', "en");
        $translate = $request->input('translate', false);
        $lastMessageCreatedAt = null;

        if ($whatsapp_message_id) {
            $lastMessage = WhatsappMessage::where('id', $whatsapp_message_id)->first();
            $lastMessageCreatedAt = $lastMessage ? \Carbon\Carbon::parse($lastMessage->created_at)->format('Y-m-d H:i:s') : null;
        }
        $query = WhatsappMessage::with('statuses.errors', 'messageable')//, 'imageMessage', 'videoMessage', 'audioMessage' // Assuming you have a 'statuses' relationship for message statuses
            ->where(function ($query) use ($phone_number_id) {
                $query->where('recipient_type', '=', 'App\Models\WhatsappConsumerPhoneNumber')
                    ->where('recipient_id', '=', $phone_number_id)
                    ->orWhere(function ($query) use ($phone_number_id) {
                        $query->where('sender_type', '=', 'App\Models\WhatsappConsumerPhoneNumber')
                            ->where('sender_id', '=', $phone_number_id);
                    });
            });
        if ($lastMessageCreatedAt) {
            $query->where('created_at', '<', $lastMessageCreatedAt);
        }

        $messages = $query->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
        if ($translate) {
            // Get text to all messages and set in one array

            $textMessages = $messages->filter(function ($message) {
                return $message->type === 'text';
            })->map(function ($message) {
                return [
                    'id' => $message->id,
                    'text' => $message->messageable->body,
                ];
            })->values()->toArray();

            $textMessagesFilter = array_column($textMessages, 'text');
            $translatedTexts = $this->translateText($textMessagesFilter, $lang);
            $translatedTextsArray = $translatedTexts['translations']; // Assuming the API returns a comma-separated string
            foreach ($textMessages as $index => &$textMessage) {
                if (isset($translatedTextsArray[$index])) {
                    $textMessage['translated_text'] = $translatedTextsArray[$index]; // Add translated text to message
                } else {
                    $textMessage['translated_text'] = null; // Set to null if no translation found
                }
            }
        }




        $messagesArray = $messages->map(function ($message) use ($textMessages, $translate) {
            if ($message->type === WhatsappMessage::MESSAGE_TYPE_TEMPLATE) {
                // If it's a template message, get the formatted body with parameters
                $formattedMessage = $this->getTemplateBodyWithParameters($message);
                $message->formatted_body = $formattedMessage;
            }
            if ($translate && $message->type == WhatsappMessage::MESSAGE_TYPE_TEXT) {
                $translatedMessage = collect($textMessages)->firstWhere('id', $message->id);
                if ($translatedMessage) {
                    $message->messageable->body_translate = $translatedMessage['translated_text'] ?? $message->messageable->body; // Use the translated text
                }
            }

            // if ($message->type === WhatsappMessage::MESSAGE_TYPE_IMAGE) {
            //     $message->media_link = $message->imageMessage->getMediaUrl();
            // }

            // if ($message->type === WhatsappMessage::MESSAGE_TYPE_VIDEO) {
            //     $message->media_link = $message->videoMessage->getSignedMediaUrlForPreview();
            // }

            // if ($message->type === WhatsappMessage::MESSAGE_TYPE_AUDIO) {
            //     $message->media_link = $message->audioMessage->getSignedMediaUrlForPreview();
            // }

            return $message->toArray(); // Convert the message to an array for the response
        })->toArray();
        usort($messagesArray, function ($a, $b) {
            return $a['messageable']['created_at'] <=> $b['messageable']['created_at'];
        });

        return $this->response(
            true,
            'Chat Messages Fetched Successfully',
            $messagesArray,
        );
    }

    public function getChatMessages(Request $request, Channel $channel, $phoneNumberId): JsonResponse
    {
        $filters = $this->extractFilters($request);
        $messages = $this->fetchMessages($channel, $phoneNumberId, $filters);

        if ($filters['translate']) {
            $organization = $channel->workspaces()?->first()?->organization;
            $extra = OrganizationWhatsappExtra::where('organization_id', $organization->id)
                ->first();

            if (!empty($extra->translation_quota) && $extra->translation_quota > 0) {
                // Fetch the wallet for the organization associated with the channel

                $mainWallet = $this->getObjectWallet($organization, Service::where('name', \App\Enums\Service::OTHER)->value('id'));

                if (!$mainWallet) {
                    return $this->response(false, 'No wallet found for this organization. Please contact support.', null, 400);
                }

                $walletBalance = (float)$mainWallet->amount;

                $translationCostPerMessage = $extra->translation_quota;  // Define the cost per message translation

                // ✅ **Step 1: Fetch only IDs of messages that need billing using ONE QUERY**
                $messagesToBillIds = WhatsappMessage::whereIn('id', $messages->pluck('id'))
                    ->whereNotExists(function ($query) use ($filters) {
                        $query->select(DB::raw(1))
                            ->from('message_translation_billings')
                            ->whereRaw('message_translation_billings.messageable_id = whatsapp_messages.id')
                            ->where('message_translation_billings.messageable_type', WhatsappMessage::class)
                            ->where('message_translation_billings.language', $filters['lang'])
                            ->where('message_translation_billings.is_billed', '=', true);
                    })
                    ->pluck('id');

                $messagesToBillCount = $messagesToBillIds->count();
                $totalTranslationCost = $messagesToBillCount * $translationCostPerMessage;

                // ✅ **Step 2: Ensure the wallet has enough funds before billing**
                if ($messagesToBillCount > 0) {
                    if ($walletBalance - $totalTranslationCost <= 0) {
                        return $this->response(false, 'Insufficient balance for translation. Please recharge your wallet.', null, 402);
                    }

                    $chargeSuccess = $this->changeBalanceOther($mainWallet, -1 * $totalTranslationCost, "Translation Quota Charge ({$messagesToBillCount} messages)");

                    if (!$chargeSuccess) {
                        return $this->response(false, 'Failed to deduct the translation amount from wallet.', null, 500);
                    }

                    // ✅ **Step 3: Bulk Insert New Billing Records**
                    $billingData = $messagesToBillIds->map(fn($id) => [
                        'messageable_id' => $id,
                        'messageable_type' => WhatsappMessage::class,
                        'language' => $filters['lang'],
                        'cost' => $translationCostPerMessage,
                        'is_billed' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->toArray();

                    MessageTranslationBilling::insert($billingData);
                }


            }
            $this->translateMessages($messages, $filters['lang']);

        }

        $formattedMessages = $this->formatMessages($messages);

        return $this->response(
            true,
            'Chat Messages Fetched Successfully',
            $formattedMessages
        );
    }

    private function extractFilters(Request $request): array
    {
        $whatsappMessageId = $request->input('id');
        $lastMessageCreatedAt = null;

        if ($whatsappMessageId) {
            $lastMessage = WhatsappMessage::find($whatsappMessageId);
            $lastMessageCreatedAt = $lastMessage
                ? \Carbon\Carbon::parse($lastMessage->created_at)->format('Y-m-d H:i:s')
                : null;
        }
        $lang = $request->input('lang', "en");
        $translate = $request->input('translate', false);
        return [
            'lastMessageCreatedAt' => $lastMessageCreatedAt,
            'translate' => $request->boolean('translate'),
            'lang' =>  $request->input('lang') ?? "en",
        ];
    }

    private function fetchMessages(Channel $channel, $phoneNumberId, array $filters)
    {
        $whatsappPhoneNumber = $channel->connector->whatsappConfiguration->whatsappPhoneNumber;

        if (!$whatsappPhoneNumber) {
            // Handle the case where the phone number is missing
           return $this->response(false, 'Associated WhatsApp phone number not found.', null, 400);
        }

        // Build the query
        $query = WhatsappMessage::with(['statuses.errors', 'messageable', 'translationBilling'])
            ->where(function ($query) use ($phoneNumberId, $whatsappPhoneNumber) {
                $query->where(function ($subQuery) use ($phoneNumberId, $whatsappPhoneNumber) {
                    // When the recipient is the WhatsApp phone number, and sender is the consumer
                    $subQuery->where('recipient_type', WhatsappPhoneNumber::class)
                        ->where('recipient_id', $whatsappPhoneNumber->id)
                        ->where('sender_type', WhatsappConsumerPhoneNumber::class)
                        ->where('sender_id', $phoneNumberId);
                })
                    ->orWhere(function ($subQuery) use ($phoneNumberId, $whatsappPhoneNumber) {
                        // When the sender is the WhatsApp phone number, and recipient is the consumer
                        $subQuery->where('sender_type', WhatsappPhoneNumber::class)
                            ->where('sender_id', $whatsappPhoneNumber->id)
                            ->where('recipient_type', WhatsappConsumerPhoneNumber::class)
                            ->where('recipient_id', $phoneNumberId);
                    });
            });


        if ($filters['lastMessageCreatedAt']) {
            $query->where('created_at', '<', $filters['lastMessageCreatedAt']);
        }

        return $query->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
    }

    private function translateMessages($messages,$lang): void
    {
        $textMessages = $this->extractTextMessages($messages);
        if (empty($textMessages)) {
            return;
        }

        $translations = $this->performTranslation($textMessages,$lang);
        $translations = $translations['translations']?? [];
        foreach ($textMessages as $index => &$textMessage) {
            if (isset($translations[$index])) {
                $textMessage['translated_text'] = $translations[$index]; // Add translated text to message
            } else {
                $textMessage['translated_text'] = null; // Set to null if no translation found
            }
        }
        $this->attachTranslations($messages, $textMessages);
    }

    private function extractTextMessages($messages): array
    {
        return $messages->filter(function ($message) {
            return in_array($message->type, ['text', 'template']); // Filter both types
        })->map(function ($message) {
            return [
                'id' => $message->id,
                'text' => $message->type === 'text'
                    ? $message->messageable->body
                    : $this->getTemplateBodyWithParameters($message), // Use formatted_body for templates
            ];
        })->values()->toArray();
    }

    private function performTranslation(array $messages,$lang): array
    {
        $textsToTranslate = array_column($messages, 'text');
        return $this->translateText($textsToTranslate, $lang);
    }

    private function attachTranslations($messages, array $translationResponse): void
    {
        $translatedMessages = collect($messages)
            ->filter(fn($message) => $message->type === WhatsappMessage::MESSAGE_TYPE_TEXT || $message->type === WhatsappMessage::MESSAGE_TYPE_TEMPLATE)
            ->each(function ($message, $index) use ($translationResponse) {
                $translatedMessage = collect($translationResponse)->firstWhere('id', $message->id);

                if ($translatedMessage) {
                    $message->messageable->body_translate = $translatedMessage['translated_text'] ?? $message->messageable->body; // Use the translated text
                }
            });
    }

    private function formatMessages($messages): array
    {
        $formattedMessages = $messages->map(function ($message) {
            if ($message->type === WhatsappMessage::MESSAGE_TYPE_TEMPLATE) {
                $message->formatted_body = $this->getTemplateBodyWithParameters($message);
            }

            return $message->toArray();
        })->toArray();

        return $this->sortByCreatedAt($formattedMessages);
    }

    private function sortByCreatedAt(array $messages): array
    {
        usort($messages, function ($a, $b) {
            return $a['messageable']['created_at'] <=> $b['messageable']['created_at'];
        });

        return $messages;
    }

    public function markMessagesAsRead(Request $request, Channel $channel, $phone_number_id)
    {
        // Get the WhatsApp phone number linked to the channel
        $whatsappPhoneNumber = $channel->connector->whatsappConfiguration->whatsappPhoneNumber;

        if (!$whatsappPhoneNumber) {
            return response()->json(['error' => 'Invalid phone number or channel configuration'], 400);
        }

        // Get the consumer phone number ID from the request
        $consumerPhoneNumberID = $phone_number_id;

        // Fetch messages sent by the consumer and received by the business
        $messages = WhatsappMessage::whereWhatsappPhoneNumberId($whatsappPhoneNumber->id)->where(function ($query) use ($consumerPhoneNumberID, $whatsappPhoneNumber) {
            $query->where('recipient_type', WhatsappPhoneNumber::class)
                ->where('recipient_id', $whatsappPhoneNumber->id)
                ->where('sender_type', WhatsappConsumerPhoneNumber::class)
                ->where('sender_id', $consumerPhoneNumberID);
        })
            ->where('direction', WhatsappMessage::MESSAGE_DIRECTION_RECEIVED)
            ->where('status', WhatsappMessage::MESSAGE_STATUS_DELIVERED)
            ->get();

        foreach ($messages as $message) {
            // Mark message as read via API
            $this->markMessageAsReadApi($whatsappPhoneNumber, $message->id);

            // Update the message status to 'read' in the database
            $message->status = WhatsappMessage::MESSAGE_STATUS_READ;
            $message->save();

            // Save the message status update
            $this->saveMessageStatus($message->id, WhatsappMessage::MESSAGE_STATUS_READ);
        }

        return response()->json(['status' => 'success', 'message' => 'All eligible messages marked as read.']);
    }

    private function markMessageAsReadApi($whatsappPhoneNumber, $message_id)
    {
        $whatsappBusinessAccount = $whatsappPhoneNumber->whatsappBusinessAccount;

        if ($whatsappBusinessAccount->name == 'Dreams SMS') {
            $accessToken = Meta::ACCESS_TOKEN;
        } else {
            // Get a valid access token using the trait method
            $accessToken = $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);
        }

        if (!$accessToken) {
            return response()->json(['error' => 'Failed to get a valid access token'], 401);
        }

        // Call WhatsApp API to mark the message as read
        $response = Http::withToken($accessToken)->post("https://graph.facebook.com/v20.0/{$whatsappPhoneNumber->id}/messages", [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $message_id,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/whatsapp/delete-message/{whatsappMessage}",
     *     summary="Soft Delete a WhatsApp message",
     *     tags={"WhatsApp Chat"},
     *     description="Soft deletes a specific WhatsApp message using the provided message ID.",
     *     @OA\Parameter(
     *         name="whatsappMessageID",
     *         in="path",
     *         required=true,
     *         description="The ID of the WhatsApp message to be deleted",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Message soft deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Message soft deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Message not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Message not found")
     *         )
     *     )
     * )
     */
    public function deleteMessage(WhatsappMessage $whatsappMessage): JsonResponse
    {
        // If the message is found, soft delete it

        $whatsappMessage->delete(); // Soft deletes the message
        return $this->response(true, 'Message soft deleted successfully.');

    }
}
