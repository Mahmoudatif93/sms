<?php

namespace App\Http\Controllers\Whatsapp;


use App\Constants\Meta;
use App\Http\Controllers\BaseApiController;
use App\Http\Responses\ConversationMessage;
use App\Http\Responses\ValidatorErrorResponse;
use App\Models\Channel;
use App\Models\WhatsappAudioMessage;
use App\Models\WhatsappConsumerPhoneNumber;
use App\Models\WhatsappDocumentMessage;
use App\Models\WhatsappFlow;
use App\Models\WhatsappFlowMessage;
use App\Models\WhatsappImageMessage;
use App\Models\WhatsappLocationMessage;
use App\Models\WhatsappMessage;
use App\Models\WhatsappPhoneNumber;
use App\Models\WhatsappReactionMessage;
use App\Models\WhatsappTextMessage;
use App\Models\WhatsappVideoMessage;
use App\Models\AppMedia;
use App\Rules\WhatsappValidPhoneNumber;
use App\Services\FileUploadService;
use App\Models\MessageTranslation;
use App\Models\WalletTransaction;
use App\Enums\WalletTransactionStatus;
use App\Traits\BusinessTokenManager;
use App\Traits\WhatsappMediaManager;
use App\Traits\WhatsappMessageManager;
use App\Traits\WhatsappPhoneNumberManager;
use App\Traits\WhatsappTemplateManager;
use App\WhatsappMessages\ContactMessage;
use App\WhatsappMessages\ImageMessage;
use App\WhatsappMessages\LocationMessage;
use App\WhatsappMessages\TextMessage;
use Exception;
use Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Validator;
use Str;
use Carbon\Carbon;


class WhatsappMessageController extends BaseApiController
{

    use BusinessTokenManager, WhatsappPhoneNumberManager, WhatsappMessageManager, WhatsappTemplateManager, WhatsappMediaManager;

    public static function middleware(): array
    {
        return [
            new Middleware('auth:api')
        ];
    }

    public function index(Request $request, $whatsappPhoneNumberId): JsonResponse
    {

        $validator = Validator::make(
            [
                'whatsappPhoneNumberId' => $whatsappPhoneNumberId
            ],
            [
                'whatsappPhoneNumberId' => ['required', 'string', 'exists:whatsapp_phone_numbers,id'],
            ]
        );

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 422);
        }

        $messages = WhatsappMessage::where('whatsapp_phone_number_id', $whatsappPhoneNumberId)
            ->with(['messageable', 'statuses'])
            ->get();

        // Return messages as JSON response
        return $this->response(true, 'Messages Fetched Successfully.', $messages);
    }


    /**
     * @OA\Post(
     *     path="/api/whatsapp/send-message",
     *     tags={"WhatsApp Send Messages"},
     *     summary="Send a WhatsApp message",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"to", "type"},
     *             @OA\Property(property="to", type="string", example="1234567890"),
     *             @OA\Property(property="type", type="string", example="text", enum={"text", "reaction", "image", "location", "contacts", "interactive", "template"}),
     *             @OA\Property(
     *                 property="text",
     *                 type="object",
     *                 @OA\Property(property="preview_url", type="boolean", example=false),
     *                 @OA\Property(property="body", type="string", example="Hello, World!")
     *             ),
     *             @OA\Property(
     *                 property="reaction",
     *                 type="object",
     *                 @OA\Property(property="message_id", type="string", example="wamid.HBgLM..."),
     *                 @OA\Property(property="emoji", type="string", example="\uD83D\uDE00")
     *             ),
     *             @OA\Property(
     *                 property="image",
     *                 type="object",
     *                 @OA\Property(property="id", type="string", example="MEDIA-OBJECT-ID")
     *             ),
     *             @OA\Property(
     *                 property="location",
     *                 type="object",
     *                 @OA\Property(property="longitude", type="number", example=-122.4194),
     *                 @OA\Property(property="latitude", type="number", example=37.7749),
     *                 @OA\Property(property="name", type="string", example="San Francisco"),
     *                 @OA\Property(property="address", type="string", example="123 Main St, San Francisco, CA")
     *             ),
     *             @OA\Property(
     *                 property="contacts",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="addresses", type="array", @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="street", type="string", example="STREET"),
     *                         @OA\Property(property="city", type="string", example="CITY"),
     *                         @OA\Property(property="state", type="string", example="STATE"),
     *                         @OA\Property(property="zip", type="string", example="ZIP"),
     *                         @OA\Property(property="country", type="string", example="COUNTRY"),
     *                         @OA\Property(property="country_code", type="string", example="COUNTRY_CODE"),
     *                         @OA\Property(property="type", type="string", example="HOME")
     *                     )),
     *                     @OA\Property(property="birthday", type="string", example="YEAR_MONTH_DAY"),
     *                     @OA\Property(property="emails", type="array", @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="email", type="string", example="EMAIL"),
     *                         @OA\Property(property="type", type="string", example="WORK")
     *                     )),
     *                     @OA\Property(property="name", type="object", @OA\Property(property="formatted_name", type="string", example="NAME"), @OA\Property(property="first_name", type="string", example="FIRST_NAME"), @OA\Property(property="last_name", type="string", example="LAST_NAME"), @OA\Property(property="middle_name", type="string", example="MIDDLE_NAME"), @OA\Property(property="suffix", type="string", example="SUFFIX"), @OA\Property(property="prefix", type="string", example="PREFIX")),
     *                     @OA\Property(property="org", type="object", @OA\Property(property="company", type="string", example="COMPANY"), @OA\Property(property="department", type="string", example="DEPARTMENT"), @OA\Property(property="title", type="string", example="TITLE")),
     *                     @OA\Property(property="phones", type="array", @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="phone", type="string", example="PHONE_NUMBER"),
     *                         @OA\Property(property="type", type="string", example="HOME"),
     *                         @OA\Property(property="wa_id", type="string", example="PHONE_OR_WA_ID")
     *                     )),
     *                     @OA\Property(property="urls", type="array", @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="url", type="string", example="URL"),
     *                         @OA\Property(property="type", type="string", example="WORK")
     *                     ))
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="interactive",
     *                 type="object",
     *                 @OA\Property(property="type", type="string", example="button"),
     *                 @OA\Property(property="body", type="object", @OA\Property(property="text", type="string", example="optional body text")),
     *                 @OA\Property(property="footer", type="object", @OA\Property(property="text", type="string", example="optional footer text")),
     *                 @OA\Property(property="action", type="object",
     *                     @OA\Property(property="catalog_id", type="string", example="CATALOG_ID"),
     *                     @OA\Property(property="product_retailer_id", type="string", example="ID_TEST_ITEM_1"),
     *                     @OA\Property(property="sections", type="array", @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="title", type="string", example="Section Title"),
     *                         @OA\Property(property="rows", type="array", @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="string", example="section-1-item-1"),
     *                             @OA\Property(property="title", type="string", example="Section Item Title"),
     *                             @OA\Property(property="description", type="string", example="Section Item Description")
     *                         ))
     *                     ))
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="template",
     *                 type="object",
     *                 @OA\Property(property="name", type="string", example="template_name"),
     *                 @OA\Property(property="language", type="object", @OA\Property(property="code", type="string", example="language_code")),
     *                 @OA\Property(property="components", type="array", @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="type", type="string", example="body"),
     *                     @OA\Property(property="parameters", type="array", @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="type", type="string", example="text"),
     *                         @OA\Property(property="text", type="string", example="parameter_value")
     *                     ))
     *                 ))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Message sent successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request"
     *     )
     * )
     */

    public function sendMessage(Request $request)
    {
        $to = $request->get('to');
        $type = $request->get('type');

        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $version = env('FACEBOOK_GRAPH_API_VERSION', 'v20.0');
        $fromPhoneNumberId = env('FACEBOOK_PHONE_NUMBER_ID');
        $accessToken = Meta::ACCESS_TOKEN;

        $message = null;

        switch ($type) {
            case 'text':
                $message['text'] = [
                    'preview_url' => $request->get('preview_url', false),
                    'body' => $request->get('text')
                ];

                $message = new TextMessage($to, $request->get('text'));

                break;

            case 'reaction':
                $message['reaction'] = [
                    'message_id' => $request->get('message_id'),
                    'emoji' => $request->get('emoji')
                ];
                break;

            case 'image':

                $image = $request->get('image');
                $message = new ImageMessage($to, $image['id'], $image['caption'] ?? null);

                break;
            case 'location':
                $message['location'] = [
                    'longitude' => $request->get('longitude'),
                    'latitude' => $request->get('latitude'),
                    'name' => $request->get('name'),
                    'address' => $request->get('address')
                ];

                $message = new LocationMessage($to, $request->get('location'));

                break;

            case 'contacts':
                $message['contacts'] = $request->get('contacts');

                $message = new ContactMessage($to, $request->get('contacts'));
                break;


            case 'interactive':
                $message['interactive'] = $request->get('interactive');
                break;

            case 'template':
                $message['template'] = [
                    'name' => $request->get('template_name'),
                    'language' => [
                        'code' => $request->get('language_code')
                    ],
                    'components' => $request->get('components')
                ];
                break;

            default:
                return response()->json(['error' => 'Invalid message type'], 400);
        }


        $url = "$baseUrl/$version/$fromPhoneNumberId/messages";

        $message = $message->toArray();
        $response = Http::withToken($accessToken)
            ->post($url, $message);


        return response()->json($response->json(), $response->status());


    }

    public function getMessages(Request $request)
    {

        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $version = env('FACEBOOK_GRAPH_API_VERSION', 'v20.0');
        $fromPhoneNumberId = env('FACEBOOK_PHONE_NUMBER_ID');
        $accessToken = Meta::ACCESS_TOKEN;


        $url = "$baseUrl/$version/$fromPhoneNumberId/messages";


        $response = Http::withToken($accessToken)
            ->get($url);

        return response()->json($response->json(), $response->status());


    }


    /**
     * @OA\Post(
     *     path="/api/whatsapp/send-template-message",
     *     tags={"WhatsApp Send Messages"},
     *     summary="Send a WhatsApp template message",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"template_id", "to", "components"},
     *             @OA\Property(property="template_id", type="string", example="1191689335202610"),
     *             @OA\Property(property="to", type="string", example="{{Recipient-Phone-Number}}"),
     *             @OA\Property(
     *                 property="components",
     *                 type="array",
     *                 @OA\Items(
     *                     required={"type", "parameters"},
     *                     @OA\Property(property="type", type="string", example="body"),
     *                     @OA\Property(
     *                         property="parameters",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="type", type="string", example="text"),
     *                             @OA\Property(property="text", type="string", example="Hello"),
     *                             @OA\Property(
     *                                 property="currency",
     *                                 type="object",
     *                                 @OA\Property(property="fallback_value", type="string", example="$100.99"),
     *                                 @OA\Property(property="code", type="string", example="USD"),
     *                                 @OA\Property(property="amount_1000", type="integer", example=100990),
     *                             ),
     *                         ),
     *                     ),
     *                 ),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(response="200", description="Template message sent successfully"),
     *     @OA\Response(response="400", description="Bad request"),
     *     @OA\Response(response="500", description="Internal server error"),
     * )
     */

    public function sendTemplateMessage(Request $request): JsonResponse
    {

        // Validate input data
        $validationResult = $this->validateTemplateMessageRequest($request);
        if (!$validationResult['success']) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validationResult['errors']), 422);
        }

        //format number
        $fromPhoneNumberId = $request->input('from');


        $whatsappPhoneNumber = WhatsappPhoneNumber::whereId($fromPhoneNumberId)->first();
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

        // Fetch template from API
        $template = $this->fetchTemplateFromAPI($request->get('template_id'), $accessToken);
        if (!$template['success']) {
            return $this->response(false, $template['error'], $template['status']);
        }
        // Check if the template has components with variables
        $templateHasVariables = $this->templateHasVariables($template['template']);

        $toSendComponents = [];
        if ($templateHasVariables) {
            // Validate and build components only if the template has variables
            $toSendComponents = $this->validateAndBuildComponents($template['template'], $request->get('components'));
            if (!$toSendComponents['success']) {
                return $this->response(false, $toSendComponents['error'], 400);
            }
        }

        // Send the template message via WhatsApp API
        $response = $this->sendWhatsAppTemplateMessage($request, $accessToken, $toSendComponents['components'] ?? null, $template['template']['name']);
        if (!$response['success']) {
            return $this->response(false, $response['error'], $response['status']);
        }

        // Save the message and template details in the database
        $whatsappMessageWithRelations = $this->saveTemplateMessageAndComponents($request, $response['data'], $toSendComponents['components'] ?? null, $template['template']);

        $whatsappMessage = WhatsappMessage::whereId($whatsappMessageWithRelations['id'])->first();

        $whatsappMessage->updateWalletTransactionMeta($request->get('transaction_id'));

        return $this->response(
            true,
            'Template Message Sent Successfully',
            new ConversationMessage($whatsappMessage, Channel::WHATSAPP_PLATFORM),
            $response['status']
        );

    }


    /**
     * @OA\Post(
     *     path="/api/whatsapp/send-text-message",
     *     summary="Send a WhatsApp text message",
     *     tags={"WhatsApp Send Messages"},
     *     description="Sends a WhatsApp text message to a specified recipient.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="from", type="string", description="The ID of the WhatsApp phone number", example="108427225641466"),
     *             @OA\Property(property="to", type="string", description="The recipient's WhatsApp phone number", example="+1234567890"),
     *             @OA\Property(property="text", type="object", description="The text message data",
     *                 @OA\Property(property="body", type="string", description="The message body", example="Hey, it's me", maxLength=4096),
     *                 @OA\Property(property="preview_url", type="boolean", description="Attempt to preview URL", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Text Message Sent Successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Text Message Sent Successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", description="The ID of the WhatsApp message", example="wamid.HBgMMjAxMTI2MjIwODA2FQIAERgSQ0U3MzEyQTQyMUY0OTRGMjVEAA=="),
     *                 @OA\Property(property="whatsapp_phone_number_id", type="integer", example=108427225641466),
     *                 @OA\Property(property="sender_type", type="string", example="App\\Models\\WhatsappPhoneNumber"),
     *                 @OA\Property(property="sender_id", type="integer", example=108427225641466),
     *                 @OA\Property(property="recipient_type", type="string", example="App\\Models\\WhatsappConsumerPhoneNumber"),
     *                 @OA\Property(property="recipient_id", type="integer", example=1),
     *                 @OA\Property(property="sender_role", type="string", example="BUSINESS"),
     *                 @OA\Property(property="type", type="string", example="text"),
     *                 @OA\Property(property="direction", type="string", example="SENT"),
     *                 @OA\Property(property="status", type="string", example="initiated"),
     *                 @OA\Property(property="whatsapp_conversation_id", type="string", nullable=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-25T14:26:02.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-25T14:26:02.000000Z"),
     *                 @OA\Property(property="messageable_id", type="integer", example=15),
     *                 @OA\Property(property="messageable_type", type="string", example="App\\Models\\WhatsappTextMessage"),
     *                 @OA\Property(property="statuses", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=11),
     *                         @OA\Property(property="whatsapp_message_id", type="string", example="wamid.HBgMMjAxMTI2MjIwODA2FQIAERgSQ0U3MzEyQTQyMUY0OTRGMjVEAA=="),
     *                         @OA\Property(property="status", type="string", example="initiated"),
     *                         @OA\Property(property="timestamp", type="integer", example=1727274362),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-25T14:26:02.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-25T14:26:02.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(property="messageable", type="object",
     *                     @OA\Property(property="id", type="integer", example=15),
     *                     @OA\Property(property="whatsapp_message_id", type="string", example="wamid.HBgMMjAxMTI2MjIwODA2FQIAERgSQ0U3MzEyQTQyMUY0OTRGMjVEAA=="),
     *                     @OA\Property(property="body", type="string", example="Hey, it's me"),
     *                     @OA\Property(property="preview_url", type="boolean", example=null),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-25T14:26:02.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-25T14:26:02.000000Z")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error(s)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation Error(s)"),
     *             @OA\Property(property="errors", type="object", description="Validation error details")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to get a valid access token")
     *         )
     *     )
     * )
     */

    public function sendTextMessage(Request $request): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'from' => ['required', 'string', 'exists:whatsapp_phone_numbers,id'],
            'to' => ['required', 'string', new WhatsappValidPhoneNumber()],
            'text' => 'required|array',
            'text.body' => 'required|string|max:4096',
            'text.preview_url' => 'boolean'
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 422);
        }

        //format number
        $toPhoneNumber = $request->input('to');
        $fromPhoneNumberId = $request->input('from');
        $previewUrl = $request->get('text.preview_url') ?? null;

        // Get original text (what agent wrote) and translated text (what will be sent)
        $originalText = $request->input('original_text');
        $translatedTo = $request->input('translated_to');
        $body = $request->input('text.body'); // This is the translated text if translation occurred

        $whatsappPhoneNumber = WhatsappPhoneNumber::whereId($fromPhoneNumberId)->first();
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

        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $version = env('FACEBOOK_GRAPH_API_VERSION', 'v20.0');

        $accessToken = Meta::ACCESS_TOKEN;

        $url = "$baseUrl/$version/$fromPhoneNumberId/messages";

        $message = [
            "to" => $toPhoneNumber,
            "type" => WhatsappMessage::MESSAGE_TYPE_TEXT,
            "recipient_type" => "individual",
            "messaging_product" => "whatsapp",
            "text" => [
                "preview_url" => $previewUrl,
                "body" => $body,
            ]
        ];

        if ($request->has('context.message_id')) {
            $message['context'] = [
                'message_id' => $request->input('context.message_id')
            ];
        }
        $response = Http::withToken($accessToken)
            ->post($url, $message);

        if (!$response->successful()) {
            return $this->response(false, 'Something Went Wrong', new ValidatorErrorResponse($response->json()), $response->status());
        }
        $responseData = json_decode($response->body());

        $wa_id = $responseData->contacts[0]->wa_id;

        $whatsappBusinessAccountID = WhatsappPhoneNumber::whereId($fromPhoneNumberId)->value('whatsapp_business_account_id');


        $recipient = WhatsappConsumerPhoneNumber::firstOrCreate(
            [
                'phone_number' => $this->normalizePhoneNumber($toPhoneNumber),
                'whatsapp_business_account_id' => $whatsappBusinessAccountID
            ],
            [
                'wa_id' => $wa_id,
            ]
        );


        $whatsappMessage = WhatsappMessage::create([
            'id' => $responseData->messages[0]->id,
            'whatsapp_phone_number_id' => $fromPhoneNumberId,
            'sender_type' => WhatsappPhoneNumber::class,
            'sender_id' => $fromPhoneNumberId,
            'agent_id' => auth('api')->user()->id ?? null,
            'recipient_id' => $recipient->id,
            'recipient_type' => get_class($recipient),
            'sender_role' => WhatsappMessage::MESSAGE_SENDER_ROLE_BUSINESS,
            'replied_to_message_id' => $request->has('context.message_id') ? $request->input('context.message_id') : null,
            'type' => WhatsappMessage::MESSAGE_TYPE_TEXT,
            'direction' => WhatsappMessage::MESSAGE_DIRECTION_SENT,
            'status' => WhatsappMessage::MESSAGE_STATUS_INITIATED,
            'conversation_id' => $request->get('conversation_id'),
        ]);

        // Save original text in database (what agent wrote), translated text was sent to WhatsApp
        $whatsappTextMessage = WhatsappTextMessage::create([
            'whatsapp_message_id' => $responseData->messages[0]->id,
            'body' => $originalText ?? $body, // Save original text if available, otherwise the sent text
            'preview_url' => $previewUrl,
        ]);

        // Update the messageable relation in the WhatsappMessage
        $didUpdate = $whatsappMessage->update([
            'messageable_id' => $whatsappTextMessage->id,
            'messageable_type' => WhatsappTextMessage::class,
        ]);

        // If message was translated, save the translation record and billing
        if ($originalText && $body !== $originalText) {
            MessageTranslation::create([
                'messageable_id' => $whatsappMessage->id,
                'messageable_type' => WhatsappMessage::class,
                'source_language' => null, // Agent's language is unknown
                'target_language' => $translatedTo,
                'translated_text' => $body, // The translated text that was sent
            ]);

            // Finalize translation billing if transaction was created
            $translationTransactionId = $request->input('translation_transaction_id');
            if ($translationTransactionId) {
                $transaction = WalletTransaction::find($translationTransactionId);
                if ($transaction && $transaction->status === WalletTransactionStatus::PENDING) {
                    // Confirm the funds
                    $wallet = $transaction->wallet()->lockForUpdate()->first();
                    $wallet->pending_amount -= abs($transaction->amount);
                    $wallet->amount -= abs($transaction->amount);
                    $wallet->save();

                    $transaction->status = WalletTransactionStatus::ACTIVE;
                    $transaction->description = 'Confirmed Translation';
                    $transaction->save();

                    // Create billing record for translation
                    \App\Models\MessageBilling::create([
                        'messageable_id' => $whatsappMessage->id,
                        'messageable_type' => WhatsappMessage::class,
                        'type' => \App\Models\MessageBilling::TYPE_TRANSLATION,
                        'cost' => $transaction->amount * -1,
                        'is_billed' => true,
                    ]);
                }
            }
        }

        // Save New Message Status
        $this->saveMessageStatus(
            (string) $whatsappMessage->id,
            WhatsappMessage::MESSAGE_STATUS_INITIATED
        );


        return $this->response(
            true,
            'Text Message Sent Successfully',
            new ConversationMessage($whatsappMessage, Channel::WHATSAPP_PLATFORM),
            $response->status()
        );


    }


    public function sendFlowMessage(Request $request): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'from' => ['required', 'string', 'exists:whatsapp_phone_numbers,id'],
            'to' => ['required', 'string', new WhatsappValidPhoneNumber()],
            'flow.flow_id' => ['required', 'string', 'exists:whatsapp_flows,id'],
            'flow.header_text' => 'required|string',
            'flow.body_text' => 'required|string',
            'flow.footer_text' => 'required|string|max:60',
            'flow.flow_cta' => 'required|string',
            'screen' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 422);
        }


        $recipientPhoneNumber = $request->input('to');
        $flowID = $request->input('flow.flow_id');
        $headerText = $request->input('flow.header_text');
        $bodyText = $request->input('flow.body_text');
        $footerText = $request->input('flow.footer_text');
        $flowCTA = $request->input('flow.flow_cta');
        $screenID = $request->input('screen');
        $flowToken = Str::uuid()->toString();

        $flowPayload = [
            'type' => 'flow',
            'header' => ['type' => 'text', 'text' => $headerText],
            'body' => ['text' => $bodyText],
            'footer' => ['text' => $footerText],
            'action' => [
                'name' => 'flow',
                'parameters' => [
                    // get it from Flow
                    'flow_message_version' => '3',
                    'flow_token' => $flowToken,
                    'flow_id' => $flowID,
                    'flow_cta' => $flowCTA,
                    'flow_action' => 'navigate',
                    'flow_action_payload' => [
                        'screen' => $screenID
                    ],
                ],
            ],
        ];



        //format number
        $toPhoneNumber = $request->input('to');
        $fromPhoneNumberId = $request->input('from');

        $whatsappPhoneNumber = WhatsappPhoneNumber::whereId($fromPhoneNumberId)->first();
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

        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $version = "v23.0";

        $accessToken = Meta::ACCESS_TOKEN;

        $url = "$baseUrl/$version/$fromPhoneNumberId/messages";

        $message = [
            "to" => $toPhoneNumber,
            "type" => WhatsappMessage::MESSAGE_TYPE_INTERACTIVE,
            "recipient_type" => "individual",
            "messaging_product" => "whatsapp",
            'interactive' => $flowPayload,
        ];


        $response = Http::withToken($accessToken)
            ->post($url, $message);


        if (!$response->successful()) {
            return $this->response(false, 'Something Went Wrong', new ValidatorErrorResponse($response->json()), $response->status());
        }
        $responseData = json_decode($response->body());

        $wa_id = $responseData->contacts[0]->wa_id;

        $whatsappBusinessAccountID = WhatsappPhoneNumber::whereId($fromPhoneNumberId)->value('whatsapp_business_account_id');


        $recipient = WhatsappConsumerPhoneNumber::firstOrCreate(
            [
                'phone_number' => $this->normalizePhoneNumber($toPhoneNumber),
                'whatsapp_business_account_id' => $whatsappBusinessAccountID
            ],
            [
                'wa_id' => $wa_id,
            ]
        );


        $whatsappMessage = WhatsappMessage::create([
            'id' => $responseData->messages[0]->id,
            'whatsapp_phone_number_id' => $fromPhoneNumberId,
            'sender_type' => WhatsappPhoneNumber::class,
            'sender_id' => $fromPhoneNumberId,
            'recipient_id' => $recipient->id,
            'recipient_type' => get_class($recipient),
            'sender_role' => WhatsappMessage::MESSAGE_SENDER_ROLE_BUSINESS,
            'type' => WhatsappMessage::MESSAGE_TYPE_INTERACTIVE,
            'direction' => WhatsappMessage::MESSAGE_DIRECTION_SENT,
            'status' => WhatsappMessage::MESSAGE_STATUS_INITIATED,
            'conversation_id' => $request->get('conversation_id'),
        ]);

        $whatsappFlowMessage = WhatsappFlowMessage::create([
            'whatsapp_message_id' => $responseData->messages[0]->id,
            'whatsapp_flow_id' => $flowID,
            'header_text' => $headerText,
            'body_text' => $bodyText,
            'footer_text' => $footerText,
            'flow_cta' => $flowCTA,
            'flow_token' => $flowToken,
            'screen_id' => $screenID,
        ]);

        // Update the messageable relation in the WhatsappMessage
        $didUpdate = $whatsappMessage->update([
            'messageable_id' => $whatsappFlowMessage->id,
            'messageable_type' => WhatsappFlowMessage::class,
        ]);

        // Save New Message Status
        $this->saveMessageStatus(
            (string) $whatsappMessage->id,
            WhatsappMessage::MESSAGE_STATUS_INITIATED
        );


        return $this->response(
            true,
            'Text Message Sent Successfully',
            new ConversationMessage($whatsappMessage, Channel::WHATSAPP_PLATFORM),
            $response->status()
        );


    }




    //

    //        /*
//         *
//         *   @todo Check from number from api if it exists and database (DOne) if it exists and belongs to the current access_token and format (DOne)
//         */
//
//


    //        // Check for billing
//
//        //Get From Webhook
//        // Get from Webhook read
//


    /**
     * @OA\Post(
     *     path="/api/whatsapp/send-image-message",
     *     summary="Send a WhatsApp image message",
     *     tags={"WhatsApp Send Messages"},
     *     description="Sends an image message to a specified WhatsApp recipient.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="from", type="string", description="The ID of the WhatsApp phone number", example="108427225641466"),
     *             @OA\Property(property="to", type="string", description="The recipient's WhatsApp phone number", example="+1234567890"),
     *             @OA\Property(property="image", type="object", description="The image message data",
     *                 @OA\Property(property="link", type="string", description="The URL to the image", example="https://example.com/images/image.jpg"),
     *                 @OA\Property(property="caption", type="string", description="Optional caption for the image", example="Check out this cool image", maxLength=1024)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Image Message Sent Successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Image Message Sent Successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", description="The ID of the WhatsApp message", example="wamid.HBgMMjAxMTI2MjIwODA2FQIAERgSQ0U3MzEyQTQyMUY0OTRGMjVEAA=="),
     *                 @OA\Property(property="whatsapp_phone_number_id", type="integer", example=108427225641466),
     *                 @OA\Property(property="sender_type", type="string", example="App\\Models\\WhatsappPhoneNumber"),
     *                 @OA\Property(property="sender_id", type="integer", example=108427225641466),
     *                 @OA\Property(property="recipient_type", type="string", example="App\\Models\\WhatsappConsumerPhoneNumber"),
     *                 @OA\Property(property="recipient_id", type="integer", example=1),
     *                 @OA\Property(property="sender_role", type="string", example="BUSINESS"),
     *                 @OA\Property(property="type", type="string", example="image"),
     *                 @OA\Property(property="direction", type="string", example="SENT"),
     *                 @OA\Property(property="status", type="string", example="initiated"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-30T14:26:02.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-30T14:26:02.000000Z"),
     *                 @OA\Property(property="messageable_id", type="integer", example=15),
     *                 @OA\Property(property="messageable_type", type="string", example="App\\Models\\WhatsappImageMessage"),
     *                 @OA\Property(property="statuses", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=11),
     *                         @OA\Property(property="whatsapp_message_id", type="string", example="wamid.HBgMMjAxMTI2MjIwODA2FQIAERgSQ0U3MzEyQTQyMUY0OTRGMjVEAA=="),
     *                         @OA\Property(property="status", type="string", example="initiated"),
     *                         @OA\Property(property="timestamp", type="integer", example=1727274362),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-30T14:26:02.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-30T14:26:02.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(property="messageable", type="object",
     *                     @OA\Property(property="id", type="integer", example=15),
     *                     @OA\Property(property="whatsapp_message_id", type="string", example="wamid.HBgMMjAxMTI2MjIwODA2FQIAERgSQ0U3MzEyQTQyMUY0OTRGMjVEAA=="),
     *                     @OA\Property(property="link", type="string", example="https://example.com/images/image.jpg"),
     *                     @OA\Property(property="caption", type="string", example="Check out this cool image"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-30T14:26:02.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-30T14:26:02.000000Z")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error(s)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation Error(s)"),
     *             @OA\Property(property="errors", type="object", description="Validation error details")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to get a valid access token")
     *         )
     *     )
     * )
     */

    public function sendImageMessage(Request $request): JsonResponse
    {
        // Validation for image message
        $validator = Validator::make($request->all(), [
            'from' => ['required', 'string', 'exists:whatsapp_phone_numbers,id'],
            'to' => ['required', 'string', new WhatsappValidPhoneNumber()],
            'image' => 'required|array',
            'image.link' => 'nullable|integer', // The AppMedia ID if linking to existing media
            'image.file' => 'nullable|file|mimes:jpeg,jpg,png|max:5120', // Direct file upload (max 5MB)
            'image.caption' => 'nullable|string|max:1024', // Caption text, optional
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 422);
        }

        // Check if either 'file' or 'link' is provided for the image
        $hasFile = $request->hasFile('image.file');
        $hasLink = !empty($request->input('image.link'));

        if (!$hasFile && !$hasLink) {
            return response()->json(['error' => 'Either file or link must be provided'], 400);
        }

        $toPhoneNumber = $request->input('to');
        $fromPhoneNumberId = $request->input('from');
        $caption = $request->input('image.caption', null);

        $whatsappPhoneNumber = WhatsappPhoneNumber::whereId($fromPhoneNumberId)->first();
        $whatsappBusinessAccount = $whatsappPhoneNumber->whatsappBusinessAccount;
        // Get access token
        $accessToken = $whatsappBusinessAccount->name == 'Dreams SMS' ? Meta::ACCESS_TOKEN : $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);

        if (!$accessToken) {
            return response()->json(['error' => 'Failed to get a valid access token'], 401);
        }

        $appMedia = null;
        $media = null;

        // If file is provided, upload it to OSS first (ignore link)
        if ($hasFile) {
            $file = $request->file('image.file');

            // Create AppMedia record
            $appMedia = AppMedia::create([
                'user_identifier' => auth('api')->user()->id ?? 'anonymous_' . time(),
            ]);

            // Upload to OSS using Spatie Media Library
            $media = $appMedia
                ->addMedia($file)
                ->usingFileName(
                    'whatsapp_image_' . time() . '.' . $file->getClientOriginalExtension()
                )
                ->toMediaCollection('whatsapp-images', 'oss');

            $imageLink = $media->getTemporaryUrl(Carbon::now()->addMinutes(5));
        } else {
            // Use existing AppMedia from link
            $appMedia = AppMedia::whereId($request->input('image.link'))->first();
            $media = $appMedia->getMedia('*')->first();
            if (empty($media)) {
                return response()->json(['error' => 'No Image Found'], 400);
            }
            $imageLink = $media->getTemporaryUrl(Carbon::now()->addMinutes(5));
        }
        // $imageLink = $request->input('image.link');

        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $version = env('FACEBOOK_GRAPH_API_VERSION', 'v20.0');
        $url = "$baseUrl/$version/$fromPhoneNumberId/messages";

        // Structure the image message payload
        $message = [
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => $toPhoneNumber,
            "type" => "image",
            "image" => []
        ];

        if (!empty($imageLink)) {
            $message['image']['link'] = $imageLink;
        }

        $imageLinkSave = $media->getUrl();

        if (!empty($caption)) {
            $message['image']['caption'] = $caption;
        }
        try {
            $response = Http::retry(3, 100)->timeout(30)->withToken($accessToken)->post($url, $message);

            if (!$response->successful()) {
                return $this->response(false, 'Something Went Wrong', new ValidatorErrorResponse($response->json()), $response->status());
            }

            $responseData = json_decode($response->body());
        } catch (Exception $e) {
            return $this->response(false, 'Connection failed. Please try again.', new ValidatorErrorResponse($e->getMessage()), 500);
        }

        $wa_id = $responseData->contacts[0]->wa_id;
        $whatsappBusinessAccountID = WhatsappPhoneNumber::whereId($fromPhoneNumberId)->value('whatsapp_business_account_id');

        $recipient = WhatsappConsumerPhoneNumber::firstOrCreate(
            [
                'phone_number' => $this->normalizePhoneNumber($toPhoneNumber),
                'whatsapp_business_account_id' => $whatsappBusinessAccountID
            ],
            [
                'wa_id' => $wa_id,
            ]
        );

        // Save the message
        $whatsappMessage = WhatsappMessage::create([
            'id' => $responseData->messages[0]->id,
            'whatsapp_phone_number_id' => $fromPhoneNumberId,
            'sender_type' => WhatsappPhoneNumber::class,
            'sender_id' => $fromPhoneNumberId,
            'agent_id' => auth('api')->user()->id ?? null,
            'recipient_id' => $recipient->id,
            'recipient_type' => get_class($recipient),
            'sender_role' => WhatsappMessage::MESSAGE_SENDER_ROLE_BUSINESS,
            'type' => WhatsappMessage::MESSAGE_TYPE_IMAGE,
            'direction' => WhatsappMessage::MESSAGE_DIRECTION_SENT,
            'status' => WhatsappMessage::MESSAGE_STATUS_INITIATED,
            'conversation_id' => $request->get('conversation_id'),
        ]);

        // Save the image message specific details
        $whatsappImageMessage = WhatsappImageMessage::create([
            'whatsapp_message_id' => $responseData->messages[0]->id,
            'link' => $imageLinkSave ?? null,
            'media_id' => $media->id,
            'caption' => $caption ?? null
        ]);

        // Update the messageable relation in the WhatsappMessage
        $whatsappMessage->update([
            'messageable_id' => $whatsappImageMessage->id,
            'messageable_type' => WhatsappImageMessage::class,
        ]);

        // Add the image to Spatie Media Library by directly using the image content
        if (!empty($media)) {
            $media->model_type = WhatsappImageMessage::class;
            $media->model_id = $whatsappImageMessage->id;
            $media->save();
            $appMedia->delete();
        }

        // Save New Message Status
        $this->saveMessageStatus($whatsappMessage->id, WhatsappMessage::MESSAGE_STATUS_INITIATED);

        // Eager load statuses and messageable relations
        // $whatsappMessageWithRelations = WhatsappMessage::with('statuses', 'messageable', 'imageMessage')->find($whatsappMessage->id);
        // // If the message has an associated image, generate the signed URL
        // if ($whatsappMessageWithRelations && $whatsappMessageWithRelations->imageMessage) {
        //     $whatsappMessageWithRelations->media_link = $whatsappMessageWithRelations->imageMessage->getSignedMediaUrlForPreview();
        // }

        return $this->response(
            true,
            'Image Message Sent Successfully',
            new ConversationMessage($whatsappMessage, Channel::WHATSAPP_PLATFORM),
            $response->status()
        );
    }

    /**
     * Send multiple files (images, videos, audios, documents) in a single request
     */
    public function sendFilesMessage(Request $request): JsonResponse
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'from' => ['required', 'string', 'exists:whatsapp_phone_numbers,id'],
            'to' => ['required', 'string', new WhatsappValidPhoneNumber()],
            'files' => 'required|array|min:1',
            'files.*.type' => 'required|string|in:image,video,audio,document',
            'files.*.file' => 'required|file',
            'files.*.caption' => 'nullable|string|max:1024',
            'files.*.link' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 422);
        }

        $toPhoneNumber = $request->input('to');
        $fromPhoneNumberId = $request->input('from');
        $conversationId = $request->input('conversation_id');
        $files = $request->input('files');

        $whatsappPhoneNumber = WhatsappPhoneNumber::whereId($fromPhoneNumberId)->first();
        $whatsappBusinessAccount = $whatsappPhoneNumber->whatsappBusinessAccount;

        // Get access token
        $accessToken = $whatsappBusinessAccount->name == 'Dreams SMS'
            ? Meta::ACCESS_TOKEN
            : $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);

        if (!$accessToken) {
            return response()->json(['error' => 'Failed to get a valid access token'], 401);
        }

        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $version = env('FACEBOOK_GRAPH_API_VERSION', 'v20.0');
        $url = "$baseUrl/$version/$fromPhoneNumberId/messages";

        $whatsappBusinessAccountID = WhatsappPhoneNumber::whereId($fromPhoneNumberId)->value('whatsapp_business_account_id');

        $sentMessages = [];
        $errors = [];

        foreach ($files as $index => $fileData) {
            try {
                $fileType = $fileData['type'];
                $caption = $fileData['caption'] ?? null;
                $uploadedFile = $request->file("files.{$index}.file");

                if (!$uploadedFile) {
                    $errors[] = "File at index {$index} is missing";
                    continue;
                }

                // Create AppMedia and upload to OSS
                $appMedia = AppMedia::create([
                    'user_identifier' => auth('api')->user()->id ?? 'anonymous_' . time(),
                ]);

                $collectionName = "whatsapp-{$fileType}s";
                $media = $appMedia
                    ->addMedia($uploadedFile)
                    ->usingFileName(
                        "whatsapp_{$fileType}_" . time() . '_' . $index . '.' . $uploadedFile->getClientOriginalExtension()
                    )
                    ->toMediaCollection($collectionName, 'oss');

                $mediaLink = $media->getTemporaryUrl(Carbon::now()->addMinutes(5));
                $mediaLinkSave = $media->getUrl();

                // Build WhatsApp message payload
                $message = [
                    "messaging_product" => "whatsapp",
                    "recipient_type" => "individual",
                    "to" => $toPhoneNumber,
                    "type" => $fileType,
                    $fileType => [
                        "link" => $mediaLink
                    ]
                ];

                if (!empty($caption)) {
                    $message[$fileType]['caption'] = $caption;
                }

                // For documents, add filename
                if ($fileType === 'document') {
                    $message[$fileType]['filename'] = $uploadedFile->getClientOriginalName();
                }

                // Send to WhatsApp API
                $response = Http::retry(3, 100)->timeout(30)->withToken($accessToken)->post($url, $message);

                if (!$response->successful()) {
                    $errors[] = "Failed to send {$fileType} at index {$index}: " . json_encode($response->json());
                    $appMedia->delete();
                    continue;
                }

                $responseData = json_decode($response->body());
                $wa_id = $responseData->contacts[0]->wa_id;

                // Get or create recipient
                $recipient = WhatsappConsumerPhoneNumber::firstOrCreate(
                    [
                        'phone_number' => $this->normalizePhoneNumber($toPhoneNumber),
                        'whatsapp_business_account_id' => $whatsappBusinessAccountID
                    ],
                    ['wa_id' => $wa_id]
                );

                // Create WhatsApp message record
                $messageTypeConstant = match ($fileType) {
                    'image' => WhatsappMessage::MESSAGE_TYPE_IMAGE,
                    'video' => WhatsappMessage::MESSAGE_TYPE_VIDEO,
                    'audio' => WhatsappMessage::MESSAGE_TYPE_AUDIO,
                    'document' => WhatsappMessage::MESSAGE_TYPE_DOCUMENT,
                };

                $whatsappMessage = WhatsappMessage::create([
                    'id' => $responseData->messages[0]->id,
                    'whatsapp_phone_number_id' => $fromPhoneNumberId,
                    'sender_type' => WhatsappPhoneNumber::class,
                    'sender_id' => $fromPhoneNumberId,
                    'agent_id' => auth('api')->user()->id ?? null,
                    'recipient_id' => $recipient->id,
                    'recipient_type' => get_class($recipient),
                    'sender_role' => WhatsappMessage::MESSAGE_SENDER_ROLE_BUSINESS,
                    'type' => $messageTypeConstant,
                    'direction' => WhatsappMessage::MESSAGE_DIRECTION_SENT,
                    'status' => WhatsappMessage::MESSAGE_STATUS_INITIATED,
                    'conversation_id' => $conversationId,
                ]);

                // Create type-specific message record
                $messageableData = [
                    'whatsapp_message_id' => $responseData->messages[0]->id,
                    'link' => $mediaLinkSave,
                    'media_id' => $media->id,
                    'caption' => $caption,
                ];

                $messageable = match ($fileType) {
                    'image' => WhatsappImageMessage::create($messageableData),
                    'video' => WhatsappVideoMessage::create($messageableData),
                    'audio' => WhatsappAudioMessage::create($messageableData),
                    'document' => WhatsappDocumentMessage::create(array_merge($messageableData, [
                        'filename' => $uploadedFile->getClientOriginalName()
                    ])),
                };

                $messageableClass = match ($fileType) {
                    'image' => WhatsappImageMessage::class,
                    'video' => WhatsappVideoMessage::class,
                    'audio' => WhatsappAudioMessage::class,
                    'document' => WhatsappDocumentMessage::class,
                };

                // Update messageable relation
                $whatsappMessage->update([
                    'messageable_id' => $messageable->id,
                    'messageable_type' => $messageableClass,
                ]);

                // Move media to the messageable model
                $media->model_type = $messageableClass;
                $media->model_id = $messageable->id;
                $media->save();
                $appMedia->delete();

                // Save message status
                $this->saveMessageStatus($whatsappMessage->id, WhatsappMessage::MESSAGE_STATUS_INITIATED);

                $sentMessages[] = new ConversationMessage($whatsappMessage, Channel::WHATSAPP_PLATFORM);

            } catch (Exception $e) {
                $errors[] = "Error processing file at index {$index}: " . $e->getMessage();
            }
        }

        if (empty($sentMessages)) {
            return $this->response(false, 'Failed to send any files', ['errors' => $errors], 400);
        }

        return $this->response(
            true,
            count($sentMessages) . ' file(s) sent successfully',
            [
                'messages' => $sentMessages,
                'errors' => $errors
            ],
            200
        );
    }


    /**
     * @OA\Post(
     *     path="/api/whatsapp/send-video-message",
     *     summary="Send a WhatsApp video message",
     *     tags={"WhatsApp Send Messages"},
     *     description="Sends a video message to a specified WhatsApp recipient.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="from", type="string", description="The ID of the WhatsApp phone number", example="108427225641466"),
     *             @OA\Property(property="to", type="string", description="The recipient's WhatsApp phone number", example="+1234567890"),
     *             @OA\Property(property="video", type="object", description="The video message data",
     *                 @OA\Property(property="id", type="string", description="The media ID for the uploaded video", example="1166846181421424"),
     *                 @OA\Property(property="link", type="string", description="The URL to the video (if not using uploaded media)", example="https://example.com/videos/video.mp4"),
     *                 @OA\Property(property="caption", type="string", description="Optional caption for the video", example="Check out this cool video", maxLength=1024)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Video Message Sent Successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Video Message Sent Successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", description="The ID of the WhatsApp message", example="wamid.HBgMMjAxMTI2MjIwODA2FQIAERgSQ0U3MzEyQTQyMUY0OTRGMjVEAA=="),
     *                 @OA\Property(property="whatsapp_phone_number_id", type="integer", example=108427225641466),
     *                 @OA\Property(property="sender_type", type="string", example="App\\Models\\WhatsappPhoneNumber"),
     *                 @OA\Property(property="sender_id", type="integer", example=108427225641466),
     *                 @OA\Property(property="recipient_type", type="string", example="App\\Models\\WhatsappConsumerPhoneNumber"),
     *                 @OA\Property(property="recipient_id", type="integer", example=1),
     *                 @OA\Property(property="sender_role", type="string", example="BUSINESS"),
     *                 @OA\Property(property="type", type="string", example="video"),
     *                 @OA\Property(property="direction", type="string", example="SENT"),
     *                 @OA\Property(property="status", type="string", example="initiated"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-30T14:26:02.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-30T14:26:02.000000Z"),
     *                 @OA\Property(property="messageable_id", type="integer", example=15),
     *                 @OA\Property(property="messageable_type", type="string", example="App\\Models\\WhatsappVideoMessage"),
     *                 @OA\Property(property="statuses", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=11),
     *                         @OA\Property(property="whatsapp_message_id", type="string", example="wamid.HBgMMjAxMTI2MjIwODA2FQIAERgSQ0U3MzEyQTQyMUY0OTRGMjVEAA=="),
     *                         @OA\Property(property="status", type="string", example="initiated"),
     *                         @OA\Property(property="timestamp", type="integer", example=1727274362),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-30T14:26:02.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-30T14:26:02.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(property="messageable", type="object",
     *                     @OA\Property(property="id", type="integer", example=15),
     *                     @OA\Property(property="whatsapp_message_id", type="string", example="wamid.HBgMMjAxMTI2MjIwODA2FQIAERgSQ0U3MzEyQTQyMUY0OTRGMjVEAA=="),
     *                     @OA\Property(property="media_id", type="string", example="1166846181421424"),
     *                     @OA\Property(property="link", type="string", example="https://example.com/videos/video.mp4"),
     *                     @OA\Property(property="caption", type="string", example="Check out this cool video"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-30T14:26:02.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-30T14:26:02.000000Z")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error(s)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation Error(s)"),
     *             @OA\Property(property="errors", type="object", description="Validation error details")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to get a valid access token")
     *         )
     *     )
     * )
     */

    public function sendVideoMessage(Request $request): JsonResponse
    {
        // Validation for video message
        $validator = Validator::make($request->all(), [
            'from' => ['required', 'string', 'exists:whatsapp_phone_numbers,id'],
            'to' => ['required', 'string', new WhatsappValidPhoneNumber()],
            'video' => 'required|array',
            'video.link' => 'nullable|integer', // The URL if linking to external media
            'video.caption' => 'nullable|string|max:1024', // Caption text, optional
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 422);
        }

        // Check if either 'id' or 'link' is provided for the video
        if (empty($request->input('video.link'))) {
            return response()->json(['error' => 'Link must be provided'], 400);
        }

        $videoLink = $request->input('video.link');


        $toPhoneNumber = $request->input('to');
        $fromPhoneNumberId = $request->input('from');
        $videoId = $request->input('video.id');
        $videoLink = $request->input('video.link');
        $caption = $request->input('video.caption', null);

        $whatsappPhoneNumber = WhatsappPhoneNumber::whereId($fromPhoneNumberId)->first();
        $whatsappBusinessAccount = $whatsappPhoneNumber->whatsappBusinessAccount;

        $appMedia = AppMedia::whereId($request->input('video.link'))->first();
        $media = $appMedia->getMedia('*')->first();
        if (empty($media)) {
            return response()->json(['error' => 'No Video Found'], 400);
        }
        $videoLink = $media->getTemporaryUrl(Carbon::now()->addMinutes(5));
        $videoLinkSave = $media->getUrl();

        // Get access token
        $accessToken = $whatsappBusinessAccount->name == 'Dreams SMS' ? Meta::ACCESS_TOKEN : $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);

        if (!$accessToken) {
            return response()->json(['error' => 'Failed to get a valid access token'], 401);
        }

        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $version = env('FACEBOOK_GRAPH_API_VERSION', 'v20.0');
        $url = "$baseUrl/$fromPhoneNumberId/messages";

        // Structure the video message payload
        $message = [
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => $toPhoneNumber,
            "type" => "video",
            "video" => []
        ];

        // Add either the media ID or the media link to the message
        if (!empty($videoLink)) {
            $message['video']['link'] = $videoLink;
        }

        if (!empty($caption)) {
            $message['video']['caption'] = $caption;
        }

        $response = Http::withToken($accessToken)->post($url, $message);

        if (!$response->successful()) {
            return $this->response(false, 'Something Went Wrong', new ValidatorErrorResponse($response->json()), $response->status());
        }

        $responseData = json_decode($response->body());

        $wa_id = $responseData->contacts[0]->wa_id;
        $whatsappBusinessAccountID = WhatsappPhoneNumber::whereId($fromPhoneNumberId)->value('whatsapp_business_account_id');

        $recipient = WhatsappConsumerPhoneNumber::firstOrCreate(
            [
                'phone_number' => $this->normalizePhoneNumber($toPhoneNumber),
                'whatsapp_business_account_id' => $whatsappBusinessAccountID
            ],
            [
                'wa_id' => $wa_id,
            ]
        );

        // Save the message
        $whatsappMessage = WhatsappMessage::create([
            'id' => $responseData->messages[0]->id,
            'whatsapp_phone_number_id' => $fromPhoneNumberId,
            'sender_type' => WhatsappPhoneNumber::class,
            'sender_id' => $fromPhoneNumberId,
            'agent_id' => auth('api')->user()->id ?? null,
            'recipient_id' => $recipient->id,
            'recipient_type' => get_class($recipient),
            'sender_role' => WhatsappMessage::MESSAGE_SENDER_ROLE_BUSINESS,
            'type' => WhatsappMessage::MESSAGE_TYPE_VIDEO,
            'direction' => WhatsappMessage::MESSAGE_DIRECTION_SENT,
            'status' => WhatsappMessage::MESSAGE_STATUS_INITIATED,
            'conversation_id' => $request->get('conversation_id'),
        ]);

        // Save the video message specific details
        $whatsappVideoMessage = WhatsappVideoMessage::create([
            'whatsapp_message_id' => $responseData->messages[0]->id,
            'link' => $videoLinkSave ?? null,
            'media_id' => $media->id ?? null,
            'caption' => $caption ?? null
        ]);

        // Update the messageable relation in the WhatsappMessage
        $whatsappMessage->update([
            'messageable_id' => $whatsappVideoMessage->id,
            'messageable_type' => WhatsappVideoMessage::class,
        ]);

        // Add the image to Spatie Media Library by directly using the image content
        if (!empty($media)) {
            $media->model_type = WhatsappVideoMessage::class;
            $media->model_id = $whatsappVideoMessage->id;
            $media->save();
            $appMedia->delete();
        }


        // Save New Message Status
        $this->saveMessageStatus($whatsappMessage->id, WhatsappMessage::MESSAGE_STATUS_INITIATED);

        return $this->response(
            true,
            'Video Message Sent Successfully',
            new ConversationMessage($whatsappMessage, Channel::WHATSAPP_PLATFORM),
            $response->status()
        );
    }


    /**
     * @OA\Post(
     *     path="/api/whatsapp/send-audio-message",
     *     summary="Send a WhatsApp audio message",
     *     tags={"WhatsApp Send Messages"},
     *     description="Sends an audio message to a specified WhatsApp recipient.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="from", type="string", description="The ID of the WhatsApp phone number", example="108427225641466"),
     *             @OA\Property(property="to", type="string", description="The recipient's WhatsApp phone number", example="+1234567890"),
     *             @OA\Property(property="audio", type="object", description="The audio message data",
     *                 @OA\Property(property="id", type="string", description="The media ID for the uploaded audio", example="3674626312780147"),
     *                 @OA\Property(property="link", type="string", description="The URL to the audio (if not using uploaded media)", example="https://example.com/audio/audio.mp3")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Audio Message Sent Successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Audio Message Sent Successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", description="The ID of the WhatsApp message", example="wamid.HBgMMjAxMTI2MjIwODA2FQIAERgSQ0U3MzEyQTQyMUY0OTRGMjVEAA=="),
     *                 @OA\Property(property="whatsapp_phone_number_id", type="integer", example=108427225641466),
     *                 @OA\Property(property="sender_type", type="string", example="App\\Models\\WhatsappPhoneNumber"),
     *                 @OA\Property(property="sender_id", type="integer", example=108427225641466),
     *                 @OA\Property(property="recipient_type", type="string", example="App\\Models\\WhatsappConsumerPhoneNumber"),
     *                 @OA\Property(property="recipient_id", type="integer", example=1),
     *                 @OA\Property(property="sender_role", type="string", example="BUSINESS"),
     *                 @OA\Property(property="type", type="string", example="audio"),
     *                 @OA\Property(property="direction", type="string", example="SENT"),
     *                 @OA\Property(property="status", type="string", example="initiated"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-30T14:26:02.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-30T14:26:02.000000Z"),
     *                 @OA\Property(property="messageable_id", type="integer", example=15),
     *                 @OA\Property(property="messageable_type", type="string", example="App\\Models\\WhatsappAudioMessage"),
     *                 @OA\Property(property="statuses", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=11),
     *                         @OA\Property(property="whatsapp_message_id", type="string", example="wamid.HBgMMjAxMTI2MjIwODA2FQIAERgSQ0U3MzEyQTQyMUY0OTRGMjVEAA=="),
     *                         @OA\Property(property="status", type="string", example="initiated"),
     *                         @OA\Property(property="timestamp", type="integer", example=1727274362),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-30T14:26:02.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-30T14:26:02.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(property="messageable", type="object",
     *                     @OA\Property(property="id", type="integer", example=15),
     *                     @OA\Property(property="whatsapp_message_id", type="string", example="wamid.HBgMMjAxMTI2MjIwODA2FQIAERgSQ0U3MzEyQTQyMUY0OTRGMjVEAA=="),
     *                     @OA\Property(property="media_id", type="string", example="3674626312780147"),
     *                     @OA\Property(property="link", type="string", example="https://example.com/audio/audio.mp3"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-30T14:26:02.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-30T14:26:02.000000Z")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error(s)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation Error(s)"),
     *             @OA\Property(property="errors", type="object", description="Validation error details")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to get a valid access token")
     *         )
     *     )
     * )
     */


    public function sendAudioMessage(Request $request): JsonResponse
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'from' => ['required', 'string', 'exists:whatsapp_phone_numbers,id'],
            'to' => ['required', 'string', new WhatsappValidPhoneNumber()],
            'audio' => 'required|array',
            'audio.link' => 'nullable|integer', // The URL if linking to external media
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 422);
        }

        // Check if either 'id' or 'link' is provided for the audio
        if (empty($request->input('audio.link'))) {
            return response()->json(['error' => 'Link must be provided'], 400);
        }

        // Extract input data
        $toPhoneNumber = $request->input('to');
        $fromPhoneNumberId = $request->input('from');
        $audioId = $request->input('audio.id');

        // Get WhatsApp phone number and business account information
        $whatsappPhoneNumber = WhatsappPhoneNumber::whereId($fromPhoneNumberId)->first();
        $whatsappBusinessAccount = $whatsappPhoneNumber->whatsappBusinessAccount;
        $appMedia = AppMedia::whereId($request->input('audio.link'))->first();
        $media = $appMedia->getMedia('whatsapp-audios')->first();

        if (empty($media)) {
            return response()->json(['error' => 'No Audio Found'], 400);
        }
        $audioLink = $media->getTemporaryUrl(Carbon::now()->addMinutes(5));
        $audioLinkSave = $media->getUrl();
        // Get access token
        $accessToken = $whatsappBusinessAccount->name == 'Dreams SMS' ? Meta::ACCESS_TOKEN : $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);

        if (!$accessToken) {
            return response()->json(['error' => 'Failed to get a valid access token'], 401);
        }

        // Set the API endpoint
        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $version = env('FACEBOOK_GRAPH_API_VERSION', 'v20.0');
        $url = "$baseUrl/$fromPhoneNumberId/messages";

        // Structure the audio message payload
        $message = [
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => $toPhoneNumber,
            "type" => "audio",
            "audio" => []
        ];

        // Add either the media ID or the media link to the message
        if (!empty($audioId)) {
            $message['audio']['id'] = $audioId; // Use WhatsApp Cloud API media
        } elseif (!empty($audioLink)) {
            $message['audio']['link'] = $audioLink; // Use OSS media link
        }

        // Send the message
        $response = Http::withToken($accessToken)->post($url, $message);

        if (!$response->successful()) {
            return $this->response(false, 'Something Went Wrong', new ValidatorErrorResponse($response->json()), $response->status());
        }

        // Parse the response
        $responseData = json_decode($response->body());

        // Extract WhatsApp user ID
        $wa_id = $responseData->contacts[0]->wa_id;

        // Create or retrieve the recipient (WhatsApp consumer phone number)
        $whatsappBusinessAccountID = WhatsappPhoneNumber::whereId($fromPhoneNumberId)->value('whatsapp_business_account_id');
        $recipient = WhatsappConsumerPhoneNumber::firstOrCreate(
            [
                'phone_number' => $this->normalizePhoneNumber($toPhoneNumber),
                'whatsapp_business_account_id' => $whatsappBusinessAccountID
            ],
            [
                'wa_id' => $wa_id,
            ]
        );

        // Save the message to the database
        $whatsappMessage = WhatsappMessage::create([
            'id' => $responseData->messages[0]->id,
            'whatsapp_phone_number_id' => $fromPhoneNumberId,
            'sender_type' => WhatsappPhoneNumber::class,
            'sender_id' => $fromPhoneNumberId,
            'agent_id' => auth('api')->user()->id ?? null,
            'recipient_id' => $recipient->id,
            'recipient_type' => get_class($recipient),
            'sender_role' => WhatsappMessage::MESSAGE_SENDER_ROLE_BUSINESS,
            'type' => WhatsappMessage::MESSAGE_TYPE_AUDIO,
            'direction' => WhatsappMessage::MESSAGE_DIRECTION_SENT,
            'status' => WhatsappMessage::MESSAGE_STATUS_INITIATED,
            'conversation_id' => $request->get('conversation_id'),
        ]);

        // Save the audio message specific details
        $whatsappAudioMessage = WhatsappAudioMessage::create([
            'whatsapp_message_id' => $responseData->messages[0]->id,
            'link' => $audioLinkSave ?? null,
            'media_id' => $audioId ?? ($media->id ?? null)
        ]);

        // Update the messageable relation in the WhatsappMessage
        $whatsappMessage->update([
            'messageable_id' => $whatsappAudioMessage->id,
            'messageable_type' => WhatsappAudioMessage::class,
        ]);

        // Add the image to Spatie Media Library by directly using the image content
        if (!empty($media)) {
            $media->model_type = WhatsappAudioMessage::class;
            $media->model_id = $whatsappAudioMessage->id;
            $media->save();
            $appMedia->delete();
        }

        // Save New Message Status
        $this->saveMessageStatus($whatsappMessage->id, WhatsappMessage::MESSAGE_STATUS_INITIATED);

        // Eager load statuses and messageable relations
        // $whatsappMessageWithRelations = WhatsappMessage::with('statuses', 'messageable', 'audioMessage')->find($whatsappMessage->id);

        // // If the message has an associated image, generate the signed URL
        // if ($whatsappMessageWithRelations && $whatsappMessageWithRelations->audioMessage) {
        //     $whatsappMessageWithRelations->media_link = $whatsappMessageWithRelations->audioMessage->getSignedMediaUrlForPreview();
        // }

        return $this->response(
            true,
            'Audio Message Sent Successfully',
            new ConversationMessage($whatsappMessage, Channel::WHATSAPP_PLATFORM),
            $response->status()
        );
    }

    public function sendLocationMessage(Request $request): JsonResponse
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'from' => ['required', 'string', 'exists:whatsapp_phone_numbers,id'],
            'to' => ['required', 'string', new WhatsappValidPhoneNumber()],
            'location.longitude' => 'required|string',
            'location.latitude' => 'required|string',
            'location.name' => 'nullable|string',
            'location.address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 422);
        }

        // Extract data
        $toPhoneNumber = $request->input('to');
        $fromPhoneNumberId = $request->input('from');
        $location = $request->input('location');

        // Fetch WhatsApp phone number and business account
        $whatsappPhoneNumber = WhatsappPhoneNumber::whereId($fromPhoneNumberId)->first();
        $whatsappBusinessAccount = $whatsappPhoneNumber->whatsappBusinessAccount;

        // Get valid access token
        $accessToken = $whatsappBusinessAccount->name === 'Dreams SMS'
            ? Meta::ACCESS_TOKEN
            : $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);

        if (!$accessToken) {
            return response()->json(['error' => 'Failed to get a valid access token'], 401);
        }

        // Set up the request to WhatsApp API
        $url = env('FACEBOOK_GRAPH_API_BASE_URL') . '/' . 'v23.0' . "/$fromPhoneNumberId/messages";
        $message = [
            "to" => $toPhoneNumber,
            "type" => "location",
            "recipient_type" => "individual",
            "messaging_product" => "whatsapp",
            "location" => [
                "longitude" => $location['longitude'],
                "latitude" => $location['latitude'],
                "name" => $location['name'] ?? null,
                "address" => $location['address'] ?? null,
            ]
        ];

        // Send the request
        $response = Http::withToken($accessToken)->post($url, $message);

        if (!$response->successful()) {
            return $this->response(false, 'Something Went Wrong', new ValidatorErrorResponse($response->json()), $response->status());
        }

        // Handle the response
        $responseData = json_decode($response->body());
        $wa_id = $responseData->contacts[0]->wa_id;

        // Save recipient phone number
        $whatsappBusinessAccountID = WhatsappPhoneNumber::whereId($fromPhoneNumberId)->value('whatsapp_business_account_id');
        $recipient = WhatsappConsumerPhoneNumber::firstOrCreate(
            [
                'phone_number' => $this->normalizePhoneNumber($toPhoneNumber),
                'whatsapp_business_account_id' => $whatsappBusinessAccountID,
            ],
            ['wa_id' => $wa_id]
        );

        // Save WhatsApp message
        $whatsappMessage = WhatsappMessage::create([
            'id' => $responseData->messages[0]->id,
            'whatsapp_phone_number_id' => $fromPhoneNumberId,
            'sender_type' => WhatsappPhoneNumber::class,
            'sender_id' => $fromPhoneNumberId,
            'agent_id' => auth('api')->user()->id ?? null,
            'recipient_id' => $recipient->id,
            'recipient_type' => get_class($recipient),
            'sender_role' => WhatsappMessage::MESSAGE_SENDER_ROLE_BUSINESS,
            'type' => WhatsappMessage::MESSAGE_TYPE_LOCATION,
            'direction' => WhatsappMessage::MESSAGE_DIRECTION_SENT,
            'status' => WhatsappMessage::MESSAGE_STATUS_INITIATED,
            'conversation_id' => $request->get('conversation_id'),
        ]);

        // Save location message details
        $whatsappLocationMessage = WhatsappLocationMessage::create([
            'whatsapp_message_id' => $responseData->messages[0]->id,
            'longitude' => $location['longitude'],
            'latitude' => $location['latitude'],
            'name' => $location['name'] ?? null,
            'address' => $location['address'] ?? null,
        ]);

        // Update the morphable relation
        $whatsappMessage->update([
            'messageable_id' => $whatsappLocationMessage->id,
            'messageable_type' => WhatsappLocationMessage::class,
        ]);

        // Save message status
        $this->saveMessageStatus(
            (string) $whatsappMessage->id,
            WhatsappMessage::MESSAGE_STATUS_INITIATED
        );

        return $this->response(
            true,
            'Location Message Sent Successfully',
            new ConversationMessage($whatsappMessage, Channel::WHATSAPP_PLATFORM),
            $response->status()
        );
    }

    public function sendDocumentMessage(Request $request): JsonResponse
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'from' => ['required', 'string', 'exists:whatsapp_phone_numbers,id'],
            'to' => ['required', 'string', new WhatsappValidPhoneNumber()],
            'document' => 'required|array',
            'document.link' => 'nullable|integer',
            'document.caption' => 'nullable|string',
            'document.filename' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 422);
        }

        if (empty($request->input('document.link'))) {
            return response()->json(['error' => 'Link must be provided'], 400);
        }

        // Extract inputs
        $toPhoneNumber = $request->input('to');
        $fromPhoneNumberId = $request->input('from');


        // Get WhatsApp number + WABA
        $whatsappPhoneNumber = WhatsappPhoneNumber::whereId($fromPhoneNumberId)->first();
        $whatsappBusinessAccount = $whatsappPhoneNumber->whatsappBusinessAccount;

        $appMedia = AppMedia::whereId($request->input('document.link'))->first();
        $media = $appMedia->getMedia('whatsapp-documents')->first();
        if (empty($media)) {
            return response()->json(['error' => 'No Document Found'], 400);
        }
        $documentLink = $media->getTemporaryUrl(Carbon::now()->addMinutes(5));
        $caption = $request->input('document.caption');
        $filename = $request->input('document.filename') ?? basename($documentLink);

        // Access token
        $accessToken = $whatsappBusinessAccount->name == 'Dreams SMS'
            ? Meta::ACCESS_TOKEN
            : $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);

        if (!$accessToken) {
            return response()->json(['error' => 'Failed to get a valid access token'], 401);
        }



        // API URL
        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $version = env('FACEBOOK_GRAPH_API_VERSION', 'v20.0');
        $url = "$baseUrl/$fromPhoneNumberId/messages";

        // Build the message payload
        $message = [
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => $toPhoneNumber,
            "type" => "document",
            "document" => []
        ];

        if (!empty($documentLink)) {
            $message['document']['link'] = $documentLink;
        }

        $documentinkSave = $media->getUrl();
        if (!empty($caption)) {
            $message['document']['caption'] = $caption;
        }

        if (!empty($filename)) {
            $message['document']['filename'] = $filename;
        }
        // Send message
        $response = Http::retry(3, 100)->timeout(30)->withToken($accessToken)->post($url, $message);

        if (!$response->successful()) {
            return $this->response(false, 'Something Went Wrong', new ValidatorErrorResponse($response->json()), $response->status());
        }

        // Parse API response
        $responseData = json_decode($response->body());
        $wa_id = $responseData->contacts[0]->wa_id;

        // Create or fetch consumer record
        $whatsappBusinessAccountID = WhatsappPhoneNumber::whereId($fromPhoneNumberId)->value('whatsapp_business_account_id');
        $recipient = WhatsappConsumerPhoneNumber::firstOrCreate(
            [
                'phone_number' => $this->normalizePhoneNumber($toPhoneNumber),
                'whatsapp_business_account_id' => $whatsappBusinessAccountID
            ],
            [
                'wa_id' => $wa_id,
            ]
        );

        // Save the whatsapp_messages record
        $whatsappMessage = WhatsappMessage::create([
            'id' => $responseData->messages[0]->id,
            'whatsapp_phone_number_id' => $fromPhoneNumberId,
            'sender_type' => WhatsappPhoneNumber::class,
            'sender_id' => $fromPhoneNumberId,
            'recipient_id' => $recipient->id,
            'recipient_type' => get_class($recipient),
            'sender_role' => WhatsappMessage::MESSAGE_SENDER_ROLE_BUSINESS,
            'type' => WhatsappMessage::MESSAGE_TYPE_DOCUMENT,
            'direction' => WhatsappMessage::MESSAGE_DIRECTION_SENT,
            'status' => WhatsappMessage::MESSAGE_STATUS_INITIATED,
            'conversation_id' => $request->get('conversation_id'),
        ]);

        // Save document-specific row
        $whatsappDocumentMessage = WhatsappDocumentMessage::create([
            'whatsapp_message_id' => $whatsappMessage->id,
            'link' => $documentinkSave ?? null,
            'media_id' => $media->id ?? null,
            'caption' => $caption,
            'filename' => $filename,
        ]);

        // Link messageable
        $whatsappMessage->update([
            'messageable_id' => $whatsappDocumentMessage->id,
            'messageable_type' => WhatsappDocumentMessage::class,
        ]);

        // Upload to Media Library (OSS)
        if (!empty($media)) {
            $media->model_type = WhatsappDocumentMessage::class;
            $media->model_id = $whatsappDocumentMessage->id;
            $media->save();
            $appMedia->delete();
        }

        // Save status
        $this->saveMessageStatus($whatsappMessage->id, WhatsappMessage::MESSAGE_STATUS_INITIATED);

        // Eager-load
        // $whatsappMessageWithRelations = WhatsappMessage::with('statuses', 'messageable')->find($whatsappMessage->id);

        // if ($whatsappMessageWithRelations && $whatsappMessageWithRelations->messageable) {
        //     $whatsappMessageWithRelations->media_link =
        //         $whatsappMessageWithRelations->messageable->getSignedMediaUrlForPreview();
        // }

        return $this->response(
            true,
            'Document Message Sent Successfully',
            new ConversationMessage($whatsappMessage, Channel::WHATSAPP_PLATFORM),
            $response->status()
        );
    }

    /**
     * Send an interactive message (button or list).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendInteractiveMessage(Request $request): JsonResponse
    {
        // Handle 'to' field - can be either string (phone number) or array (object with type)
        $toField = $request->input('to');
        $isToString = is_string($toField);

        $validator = Validator::make($request->all(), [
            'from' => ['required', 'string', 'exists:whatsapp_phone_numbers,id'],
            'to' => $isToString ? ['required', 'string', new WhatsappValidPhoneNumber()] : ['nullable', 'array'],
            'to.type' => $isToString ? [] : ['required_with:to', 'string', 'in:contact'],
            'to.contact' => $isToString ? [] : ['nullable', 'string', new WhatsappValidPhoneNumber()],
            'conversation_id' => ['required', 'string', 'exists:conversations,id'],
            'type' => ['required', 'string', 'in:interactive'],
            // Interactive object
            'interactive' => 'required|array',
            'interactive.type' => ['required', 'string', 'in:button,list'],
            // Header (optional)
            'interactive.header' => 'nullable|array',
            'interactive.header.type' => 'nullable|string|in:text,image,video,document',
            'interactive.header.text' => 'required_if:interactive.header.type,text|nullable|string|max:60',
            'interactive.header.image' => 'required_if:interactive.header.type,image|nullable|array',
            'interactive.header.image.link' => 'nullable|url',
            'interactive.header.video' => 'required_if:interactive.header.type,video|nullable|array',
            'interactive.header.video.link' => 'nullable|url',
            'interactive.header.document' => 'required_if:interactive.header.type,document|nullable|array',
            'interactive.header.document.link' => 'nullable|url',
            // Body (required)
            'interactive.body' => 'required|array',
            'interactive.body.text' => 'required|string|max:1024',
            // Footer (optional)
            'interactive.footer' => 'nullable|array',
            'interactive.footer.text' => 'nullable|string|max:60',
            // Action (required)
            'interactive.action' => 'required|array',
            // Buttons for button type (max 3)
            'interactive.action.buttons' => 'required_if:interactive.type,button|array|max:3',
            'interactive.action.buttons.*.id' => 'required_with:interactive.action.buttons|string|max:256',
            'interactive.action.buttons.*.title' => 'required_with:interactive.action.buttons|string|max:20',
            // List for list type
            'interactive.action.button' => 'required_if:interactive.type,list|nullable|string|max:20',
            'interactive.action.sections' => 'required_if:interactive.type,list|nullable|array|max:10',
            'interactive.action.sections.*.title' => 'required_with:interactive.action.sections|string|max:24',
            'interactive.action.sections.*.rows' => 'required_with:interactive.action.sections|array|max:10',
            'interactive.action.sections.*.rows.*.id' => 'required|string|max:200',
            'interactive.action.sections.*.rows.*.title' => 'required|string|max:24',
            'interactive.action.sections.*.rows.*.description' => 'nullable|string|max:72',
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 422);
        }

        $fromPhoneNumberId = $request->input('from');
        $conversationId = $request->input('conversation_id');

        // Get recipient phone number from request or conversation
        $toPhoneNumber = null;

        // If 'to' is a string, use it directly
        if (\is_string($toField)) {
            $toPhoneNumber = $toField;
        }
        // If 'to' is an array/object, try to get contact from it
        elseif (\is_array($toField) && isset($toField['contact'])) {
            $toPhoneNumber = $toField['contact'];
        }

        // If phone number not found in 'to', get it from conversation
        if (!$toPhoneNumber) {
            $conversation = \App\Models\Conversation::find($conversationId);
            if (!$conversation) {
                return $this->response(false, 'Conversation not found', [], 404);
            }

            $toPhoneNumber = $conversation->whatsapp_consumer_phone_number->phone_number ?? null;
            if (!$toPhoneNumber) {
                return $this->response(false, 'Recipient phone number not found', [], 400);
            }
        }

        // Get WhatsApp phone number and business account
        $whatsappPhoneNumber = WhatsappPhoneNumber::whereId($fromPhoneNumberId)->first();
        $whatsappBusinessAccount = $whatsappPhoneNumber->whatsappBusinessAccount;

        // Get access token
        $accessToken = $whatsappBusinessAccount->name == 'Dreams SMS'
            ? Meta::ACCESS_TOKEN
            : $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);

        if (!$accessToken) {
            return response()->json(['error' => 'Failed to get a valid access token'], 401);
        }

        // Get interactive payload directly from request
        $interactivePayload = $this->prepareInteractivePayload($request->input('interactive'));

        // API URL
        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $version = env('FACEBOOK_GRAPH_API_VERSION', 'v20.0');
        $url = "$baseUrl/$version/$fromPhoneNumberId/messages";

        $message = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toPhoneNumber,
            'type' => 'interactive',
            'interactive' => $interactivePayload,
        ];

        // Send message
        $response = Http::withToken($accessToken)->post($url, $message);

        if (!$response->successful()) {
            return $this->response(false, 'Something Went Wrong', new ValidatorErrorResponse($response->json()), $response->status());
        }

        $responseData = json_decode($response->body());
        $wa_id = $responseData->contacts[0]->wa_id;

        // Get or create recipient
        $whatsappBusinessAccountID = $whatsappPhoneNumber->whatsapp_business_account_id;
        $recipient = WhatsappConsumerPhoneNumber::firstOrCreate(
            [
                'phone_number' => $this->normalizePhoneNumber($toPhoneNumber),
                'whatsapp_business_account_id' => $whatsappBusinessAccountID,
            ],
            ['wa_id' => $wa_id]
        );

        // Create WhatsApp message
        $whatsappMessage = WhatsappMessage::create([
            'id' => $responseData->messages[0]->id,
            'whatsapp_phone_number_id' => $fromPhoneNumberId,
            'sender_type' => WhatsappPhoneNumber::class,
            'sender_id' => $fromPhoneNumberId,
            'recipient_id' => $recipient->id,
            'recipient_type' => get_class($recipient),
            'sender_role' => WhatsappMessage::MESSAGE_SENDER_ROLE_BUSINESS,
            'type' => WhatsappMessage::MESSAGE_TYPE_INTERACTIVE,
            'direction' => WhatsappMessage::MESSAGE_DIRECTION_SENT,
            'status' => WhatsappMessage::MESSAGE_STATUS_INITIATED,
            'conversation_id' => $request->get('conversation_id'),
        ]);

        // Create interactive message record
        $whatsappInteractiveMessage = \App\Models\WhatsappInteractiveMessage::create([
            'whatsapp_message_id' => $responseData->messages[0]->id,
            'interactive_type' => $request->input('interactive.type'),
            'payload' => $interactivePayload,
        ]);

        // Update messageable relation
        $whatsappMessage->update([
            'messageable_id' => $whatsappInteractiveMessage->id,
            'messageable_type' => \App\Models\WhatsappInteractiveMessage::class,
        ]);

        // Save message status
        $this->saveMessageStatus($whatsappMessage->id, WhatsappMessage::MESSAGE_STATUS_INITIATED);

        return $this->response(
            true,
            'Interactive Message Sent Successfully',
            new ConversationMessage($whatsappMessage, Channel::WHATSAPP_PLATFORM),
            $response->status()
        );
    }

    /**
     * Send a reaction message.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendReactionMessage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from' => ['required', 'string', 'exists:whatsapp_phone_numbers,id'],
            'to' => ['required', 'string', new WhatsappValidPhoneNumber()],
            'reaction.message_id' => 'required|string|exists:whatsapp_messages,id',
            'reaction.emoji' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 422);
        }

        //format number
        $toPhoneNumber = $request->input('to');
        $fromPhoneNumberId = $request->input('from');
        $messageId = $request->input('reaction.message_id');
        $emoji = $request->input('reaction.emoji');

        $whatsappPhoneNumber = WhatsappPhoneNumber::whereId($fromPhoneNumberId)->first();
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

        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $version = env('FACEBOOK_GRAPH_API_VERSION', 'v20.0');

        $accessToken = Meta::ACCESS_TOKEN;

        $url = "$baseUrl/$version/$fromPhoneNumberId/messages";

        $message = [
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => $toPhoneNumber,
            "type" => "reaction",
            "reaction" => [
                "message_id" => $messageId,
                "emoji" => $emoji ?? "",
            ]
        ];
        $response = Http::withToken($accessToken)
            ->post($url, $message);

        if (!$response->successful()) {
            return $this->response(false, 'Something Went Wrong', new ValidatorErrorResponse($response->json()), $response->status());
        }
        $responseData = json_decode($response->body());

        $wa_id = $responseData->contacts[0]->wa_id;

        $whatsappBusinessAccountID = WhatsappPhoneNumber::whereId($fromPhoneNumberId)->value('whatsapp_business_account_id');


        $recipient = WhatsappConsumerPhoneNumber::firstOrCreate(
            [
                'phone_number' => $this->normalizePhoneNumber($toPhoneNumber),
                'whatsapp_business_account_id' => $whatsappBusinessAccountID
            ],
            [
                'wa_id' => $wa_id,
            ]
        );
        if ($emoji == null || $emoji == "") {
            WhatsappReactionMessage::where('message_id', $messageId)->delete();
            return $this->response(
                true,
                'Reaction Message Sent Successfully',
                null,
                200
            );
        }
        $whatsappMessage = WhatsappMessage::create([
            'id' => $responseData->messages[0]->id,
            'whatsapp_phone_number_id' => $fromPhoneNumberId,
            'sender_type' => WhatsappPhoneNumber::class,
            'sender_id' => $fromPhoneNumberId,
            'agent_id' => auth('api')->user()->id ?? null,
            'recipient_id' => $recipient->id,
            'recipient_type' => get_class($recipient),
            'sender_role' => WhatsappMessage::MESSAGE_SENDER_ROLE_BUSINESS,
            'type' => WhatsappMessage::MESSAGE_TYPE_REACTION,
            'direction' => WhatsappMessage::MESSAGE_DIRECTION_SENT,
            'status' => WhatsappMessage::MESSAGE_STATUS_INITIATED,
            'conversation_id' => $request->get('conversation_id'),
        ]);

        $whatsappReactionMessage = WhatsappReactionMessage::create([
            'whatsapp_message_id' => $responseData->messages[0]->id,
            'message_id' => $messageId,
            'emoji' => $emoji,
            'direction' => WhatsappMessage::MESSAGE_DIRECTION_SENT
        ]);

        // Update the messageable relation in the WhatsappMessage
        $didUpdate = $whatsappMessage->update([
            'messageable_id' => $whatsappReactionMessage->id,
            'messageable_type' => WhatsappReactionMessage::class,
        ]);

        // Save New Message Status
        $this->saveMessageStatus(
            (string) $whatsappMessage->id,
            WhatsappMessage::MESSAGE_STATUS_INITIATED
        );


        return $this->response(
            true,
            'Reaction Message Sent Successfully',
            new ConversationMessage($whatsappMessage, Channel::WHATSAPP_PLATFORM),
            $response->status()
        );


    }


    /**
     * Build the interactive payload based on type.
     *
     * @param Request $request
     * @param string $type
     * @return array
     */
    private function buildInteractivePayload(Request $request, string $type): array
    {
        $payload = [
            'type' => $type,
            'body' => ['text' => $request->input('body')],
        ];

        // Add header if provided
        if ($request->has('header')) {
            $headerType = $request->input('header.type', 'text');
            $payload['header'] = ['type' => $headerType];

            switch ($headerType) {
                case 'text':
                    $payload['header']['text'] = $request->input('header.text');
                    break;
                case 'image':
                    $payload['header']['image'] = $request->input('header.image');
                    break;
                case 'video':
                    $payload['header']['video'] = $request->input('header.video');
                    break;
                case 'document':
                    $payload['header']['document'] = $request->input('header.document');
                    break;
            }
        }

        // Add footer if provided
        if ($request->filled('footer')) {
            $payload['footer'] = ['text' => $request->input('footer')];
        }

        // Build action based on type
        if ($type === 'button') {
            $payload['action'] = [
                'buttons' => collect($request->input('buttons'))->map(function ($button) {
                    return [
                        'type' => 'reply',
                        'reply' => [
                            'id' => $button['id'],
                            'title' => $button['title'],
                        ],
                    ];
                })->toArray(),
            ];
        } elseif ($type === 'list') {
            $payload['action'] = [
                'button' => $request->input('list_button_text'),
                'sections' => collect($request->input('sections'))->map(function ($section) {
                    return [
                        'title' => $section['title'],
                        'rows' => collect($section['rows'])->map(function ($row) {
                            $rowData = [
                                'id' => $row['id'],
                                'title' => $row['title'],
                            ];
                            if (!empty($row['description'])) {
                                $rowData['description'] = $row['description'];
                            }
                            return $rowData;
                        })->toArray(),
                    ];
                })->toArray(),
            ];
        }

        return $payload;
    }

    /**
     * Prepare interactive payload from request data.
     * Transforms the buttons format to WhatsApp API format.
     *
     * @param array $interactive
     * @return array
     */
    private function prepareInteractivePayload(array $interactive): array
    {
        $payload = [
            'type' => $interactive['type'],
            'body' => $interactive['body'],
        ];

        // Add header if provided
        if (isset($interactive['header'])) {
            $payload['header'] = $interactive['header'];
        }

        // Add footer if provided
        if (isset($interactive['footer'])) {
            $payload['footer'] = $interactive['footer'];
        }

        // Transform action based on type
        if (isset($interactive['action'])) {
            $payload['action'] = [];

            // For button type, transform buttons to WhatsApp format
            if ($interactive['type'] === 'button' && isset($interactive['action']['buttons'])) {
                $payload['action']['buttons'] = collect($interactive['action']['buttons'])->map(function ($button) {
                    return [
                        'type' => 'reply',
                        'reply' => [
                            'id' => $button['id'],
                            'title' => $button['title'],
                        ],
                    ];
                })->toArray();
            }

            // For list type, keep sections as is but ensure proper structure
            if ($interactive['type'] === 'list' && isset($interactive['action']['sections'])) {
                $payload['action']['button'] = $interactive['action']['button'] ?? 'Options';
                $payload['action']['sections'] = $interactive['action']['sections'];
            }
        }

        return $payload;
    }
}
