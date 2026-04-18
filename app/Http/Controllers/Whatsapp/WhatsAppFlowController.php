<?php

namespace App\Http\Controllers\Whatsapp;

use App\Constants\Meta;
use App\Http\Controllers\BaseApiController;
use App\Http\Controllers\Controller;
use App\Http\Responses\Flow;
use App\Http\Responses\FlowDetails;
use App\Http\Responses\ValidatorErrorResponse;
use App\Http\Whatsapp\WhatsappFlows\FlowValidationError;
use App\Models\Channel;
use App\Models\WhatsappFlow;
use App\Traits\BusinessTokenManager;
use App\Traits\ChannelManager;
use App\Traits\FlowsManager;
use Http;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Log;
use OpenApi\Annotations as OA;
use App\Models\WhatsappBusinessAccount;
use Str;
use Validator;

class WhatsAppFlowController extends BaseApiController
{
    use BusinessTokenManager, ChannelManager, FlowsManager;
    private string $accessToken;
    private mixed $whatsappBusinessAccountId;
    private mixed $phoneNumberId;
    private string $messagingUrl;

    public function __construct()
    {
        $this->accessToken = Meta::ACCESS_TOKEN;
        $this->whatsappBusinessAccountId = env('DREAMS_WHATSAPP_BUSINESS_ACCOUNT_ID');
        $this->phoneNumberId = env('FACEBOOK_PHONE_NUMBER_ID');
        $this->messagingUrl = "https://graph.facebook.com/v20.0/{$this->phoneNumberId}/messages";
    }

    //    public function createFlow(Request $request): JsonResponse
//    {
//        $request->validate([
//            'name' => 'required|string',
//            'category' => 'required|string',
//            'file' => 'required|file|mimes:json'
//        ]);
//
//        $name = $request->input('name');
//        $category = $request->input('category');
//        $file = $request->file('file');
//
//        $flowId = $this->createFlowAPI($name, $category);
//        $this->uploadFlowJson($flowId, $file);
//        $this->publishFlow($flowId);
//        return response()->json(['message' => 'Flow Created'], 200);
//    }

    private function createFlowAPI()
    {
        $response = Http::withToken($this->accessToken)->post("https://graph.facebook.com/v20.0/{$this->whatsappBusinessAccountId}/flows", [
            'name' => 'survey_flow',
            'categories' => '["SURVEY"]'
        ]);

        $flowId = $response->json('id');
        return $flowId;
    }

    //    private function uploadFlowJson($flowId): void
//    {
//        $response = Http::withToken($this->accessToken)->attach(
//            'file', fopen(base_path('survey.json'), 'r'), 'survey.json'
//        )->post("https://graph.facebook.com/v20.0/{$flowId}/assets", [
//            'name' => 'flow.json',
//            'asset_type' => 'FLOW_JSON'
//        ]);
//
//        Log::info('Flow JSON uploaded', $response->json());
//    }


    /**
     * @OA\Post(
     *     path="/api/whatsapp/{whatsappBusinessAccount}/flows/{flowId}/publish",
     *     operationId="publishFlow",
     *     tags={"WhatsApp Flows"},
     *     summary="Publish a WhatsApp flow",
     *     description="Publishes a WhatsApp flow using the provided flow ID.",
     *     @OA\Parameter(
     *         name="whatsappBusinessAccount",
     *         in="path",
     *         required=true,
     *         description="The ID of the WhatsApp Business Account",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="flowId",
     *         in="path",
     *         required=true,
     *         description="The ID of the flow to be published",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Flow published successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Flow published successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to get a valid access token"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Internal Server Error"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     )
     * )
     * @throws ConnectionException
     * @throws \Exception
     */
    public function publishFlow(Channel $channel, $flowId)
    {
        $channelDetails = $this->getChannelDetails($channel);

        // Check if the response from getChannelDetails is an error response
        if ($channelDetails instanceof JsonResponse) {
            return $channelDetails; // Return the error response
        }

        $accessToken = $channelDetails['access_token'];

        $response = Http::withToken($accessToken)->post("https://graph.facebook.com/v23.0/{$flowId}/publish");
        Log::info('Flow Published', $response->json());
        if ($response->successful()) {
            return $this->response(
                true,
                'The flow message has been successfully published',
                $response->json(),
                200
            );
        } else {
            $error = $response->json()['error'];
            if ($error['error_user_title'] == "Publishing without specifying 'endpoint_uri' is forbidden") {
                if ($this->updateFlowEndPointURI($channel, $flowId, $accessToken)) {
                    $response = Http::withToken($accessToken)->post("https://graph.facebook.com/v23.0/{$flowId}/publish");

                    if ($response->successful()) {
                        return $this->response(
                            true,
                            'The flow message has been successfully published',
                            $response->json());
                    }

                }
            }

            return $this->response(
                false,
                'Failed to publish flow message',
                $response->json(),
                $response->status()
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/whatsapp/{whatsappBusinessAccount}/flows/send",
     *     operationId="sendFlow",
     *     tags={"WhatsApp Flows"},
     *     summary="Send an interactive flow message",
     *     description="Send an interactive flow message via WhatsApp API.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="to", type="string", description="Recipient's phone number"),
     *             @OA\Property(property="flow_id", type="string", description="Unique Flow ID provided by WhatsApp"),
     *             @OA\Property(property="header_text", type="string", description="Dynamic text for the header"),
     *             @OA\Property(property="body_text", type="string", description="Dynamic text for the body"),
     *             @OA\Property(property="footer_text", type="string", description="Dynamic text for the footer"),
     *             @OA\Property(property="flow_cta", type="string", description="Dynamic text for the CTA button"),
     *             @OA\Property(property="screen", type="string", description="Screen ID for the first screen of the Flow"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     *
     * )
     */

    public function sendFlow(Request $request, Channel $channel): JsonResponse
    {
        $connector = $channel->connector;

        // Retrieve the phone_number_id from WhatsappConfiguration
        $whatsappConfiguration = $connector->whatsappConfiguration;

        $whatsappBusinessAccount = $whatsappConfiguration->whatsappBusinessAccount;

        $accessToken = $whatsappBusinessAccount->name == 'Dreams SMS' ?
            Meta::ACCESS_TOKEN :
            $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);

        if (!$accessToken) {
            return $this->response(false, 'Failed to get a valid access token', null, 401);
        }

        $request->validate([
            'to' => 'required|string',
            'flow_id' => 'required|string',
            'header_text' => 'required|string',
            'body_text' => 'required|string',
            'footer_text' => 'required|string',
            'flow_cta' => 'required|string',
            'screen' => 'required|string',
        ]);

        $recipientPhoneNumber = $request->input('to');
        $flowID = $request->input('flow_id');
        $headerText = $request->input('header_text');
        $bodyText = $request->input('body_text');
        $footerText = $request->input('footer_text');
        $flowCTA = $request->input('flow_cta');
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
                    'flow_message_version' => '7.2',
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

        $response = Http::withToken($accessToken)->post($this->messagingUrl, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $recipientPhoneNumber,
            'type' => 'interactive',
            'interactive' => $flowPayload,
        ]);

        if ($response->successful()) {
            return $this->response(
                true,
                'Flow message sent successfully',
                $response->json(),
                200
            );
        } else {
            return $this->response(
                false,
                'Failed to send flow message',
                $response->json(),
                $response->status()
            );
        }
    }


    /**
     * @OA\Get(
     *     path="/api/whatsapp/{whatsappBusinessAccount}/flows",
     *     operationId="getAllFlows",
     *     tags={"WhatsApp Flows"},
     *     summary="Retrieve a list of Flows",
     *     description="Retrieves a list of Flows under a WhatsApp Business Account",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="string"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="status", type="string"),
     *                     @OA\Property(property="categories", type="array", @OA\Items(type="string")),
     *                     @OA\Property(property="validation_errors", type="array", @OA\Items(type="string")),
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="paging",
     *                 @OA\Property(
     *                     property="cursors",
     *                     @OA\Property(property="before", type="string"),
     *                     @OA\Property(property="after", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     * @throws \Exception
     */
    public function getAllFlows(Channel $channel): JsonResponse
    {
        $connector = $channel->connector;

        // Retrieve the phone_number_id from WhatsappConfiguration
        $whatsappConfiguration = $connector->whatsappConfiguration;

        $whatsappBusinessAccount = $whatsappConfiguration->whatsappBusinessAccount;

        $accessToken = $whatsappBusinessAccount->name == 'Dreams SMS' ?
            Meta::ACCESS_TOKEN :
            $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);

        if (!$accessToken) {
            return $this->response(false, 'Failed to get a valid access token', null, 401);
        }

        $url = "https://graph.facebook.com/v23.0/{$whatsappBusinessAccount->id}/flows";
        $flows = collect(); // Initialize an empty collection to store all flows

        do {
            $response = Http::withToken($accessToken)->get($url);

            if ($response->successful()) {
                $data = $response->json('data');
                $flows = $flows->merge(collect($data)->map(function ($flowData) use ($channel) {
                    WhatsappFlow::updateOrCreate(
                        ['id' => $flowData['id'], 'channel_id' => $channel->id],
                        [
                            'name' => $flowData['name'],
                            'status' => $flowData['status'] ?? null,
                            'categories' => $flowData['categories'] ?? [],
                        ]
                    );
                    return new Flow($flowData);
                }));
                $paging = $response->json('paging');
                // Check if there is a 'next' page
                $url = $paging['next'] ?? null;
            } else {
                return $this->response(
                    false,
                    'Failed to retrieve flows',
                    null,
                    $response->status()
                );
            }
        } while ($url); // Continue while there is a next page

        return $this->response(
            true,
            'Flows retrieved successfully',
            $flows,
            200
        );
    }


    protected function response(
        bool $success = true,
        string $message = '',
        mixed $data = null,
        int $statusCode = 200,
        array $headers = []
    ): JsonResponse {
        $response = [
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ];

        return new JsonResponse($response, $statusCode, $headers);
    }


    /**
     * @OA\Post(
     *     path="/api/whatsapp/{whatsappBusinessAccount}/flows",
     *     operationId="createFlow",
     *     tags={"WhatsApp Flows"},
     *     summary="Create a new Flow",
     *     description="Creates a new Flow under a WhatsApp Business Account",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="name",
     *                     type="string",
     *                     description="Name of the Flow",
     *                     example="Customer Support Flow"
     *                 ),
     *                 @OA\Property(
     *                     property="categories",
     *                     type="array",
     *                     @OA\Items(
     *                         type="string",
     *                         enum={"SIGN_UP", "SIGN_IN", "APPOINTMENT_BOOKING", "LEAD_GENERATION", "CONTACT_US", "CUSTOMER_SUPPORT", "SURVEY", "OTHER"},
     *                         description="Flow categories",
     *                         example="OTHER"
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="clone_flow_id",
     *                     type="string",
     *                     description="ID of the existing Flow to clone",
     *                     example="original-flow-id"
     *                 ),
     *                 @OA\Property(
     *                     property="endpoint_uri",
     *                     type="string",
     *                     description="Endpoint URI for the Flow",
     *                     example="https://example.com"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Flow created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="string", description="ID of the created Flow")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function createFlow(Request $request, Channel $channel): JsonResponse
    {
        $channelDetails = $this->getChannelDetails($channel);

        // Check if the response from getChannelDetails is an error response
        if ($channelDetails instanceof JsonResponse) {
            return $channelDetails; // Return the error response
        }

        $whatsappBusinessAccountID = $channelDetails['whatsapp_business_account_id'];
        $accessToken = $channelDetails['access_token'];

        $url = "https://graph.facebook.com/v23.0/{$whatsappBusinessAccountID}/flows";

        $data = [
            'name' => $request->name,
            'categories' => $request->categories,
        ];

        if ($request->has('clone_flow_id')) {
            $data['clone_flow_id'] = $request->clone_flow_id;
        }

        if ($request->has('endpoint_uri')) {
            $data['endpoint_uri'] = $request->endpoint_uri;
        }

        // Add flow_template if present in the request
        if ($request->has('flow_template')) {
            $data['flow_template'] = $request->flow_template;
        }

        $response = Http::withToken($accessToken)
            ->asForm()
            ->post($url, $data);

        if ($response->successful()) {
            $data = $response->json();
            return $this->response(
                true,
                'Flow created successfully',
                $data,
                200
            );
        } else {
            return $this->response(
                false,
                $response->json()["error"]["error_user_title"],
                $response->json(),
                $response->status()
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/whatsapp/{whatsappBusinessAccount}/flows/{flowId}",
     *     summary="Update Flow",
     *     description="Updates an existing Flow.",
     *     tags={"WhatsApp Flows"},
     *     @OA\Parameter(
     *         name="flowId",
     *         in="path",
     *         required=true,
     *         description="The unique ID of the Flow.",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         description="Flow update details",
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", description="Flow name"),
     *             @OA\Property(property="categories", type="array", description="Flow categories", @OA\Items(type="string")),
     *             @OA\Property(property="endpoint_uri", type="string", description="Endpoint URI for the flow"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Flow updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/FlowDetails")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found"
     *     )
     * )
     */
    public function updateFlow(Request $request, Channel $channel, string $flowId): JsonResponse
    {
        $connector = $channel->connector;

        // Retrieve the phone_number_id from WhatsappConfiguration
        $whatsappConfiguration = $connector->whatsappConfiguration;

        $whatsappBusinessAccount = $whatsappConfiguration->whatsappBusinessAccount;

        $accessToken = $whatsappBusinessAccount->name == 'Dreams SMS' ?
            Meta::ACCESS_TOKEN :
            $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);

        if (!$accessToken) {
            return $this->response(false, 'Failed to get a valid access token', null, 401);
        }

        $url = "https://graph.facebook.com/v20.0/{$flowId}";

        $data = [
            'name' => $request->name,
            'categories' => $request->categories,
        ];

        if ($request->has('endpoint_uri')) {
            $data['endpoint_uri'] = $request->endpoint_uri;
        }

        $response = Http::withToken($accessToken)
            ->asForm()
            ->post($url, $data);

        if ($response->successful()) {
            $data = $response->json();
            return $this->response(
                true,
                'Flow updated successfully',
                ['id' => $flowId, 'success' => true],
                200
            );
        } else {
            return $this->response(
                false,
                $response->json()["error"]["message"],
                $response->json(),
                $response->status()
            );
        }
    }
    /**
     * @OA\Get(
     *     path="/api/whatsapp/{whatsappBusinessAccount}/flows/{flowId}",
     *     summary="Retrieve Flow Details",
     *     description="Returns detailed information about a specific Flow.",
     *     tags={"WhatsApp Flows"},
     *     @OA\Parameter(
     *         name="flowId",
     *         in="path",
     *         required=true,
     *         description="The unique ID of the Flow.",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful Response",
     *         @OA\JsonContent(ref="#/components/schemas/FlowDetails")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found"
     *     )
     * )
     */


    public function getFlowDetails(Channel $channel, string $flowId): JsonResponse
    {
        $channelDetails = $this->getChannelDetails($channel);

        // Check if the response from getChannelDetails is an error response
        if ($channelDetails instanceof JsonResponse) {
            return $channelDetails; // Return the error response
        }

        $accessToken = $channelDetails['access_token'];


        $url = "https://graph.facebook.com/v23.0/{$flowId}";

        $response = Http::withToken($accessToken)->get($url, [
            'fields' => 'application,categories,health_status,json_version,preview,validation_errors,assets.limit(10){asset_type,download_url,name},updated_at,status,name,id,whatsapp_business_account,data_api_version,endpoint_uri',
        ]);

        if ($response->successful()) {
            $flowDetails = $response->json();
            $flowDetailsResponse = new FlowDetails($flowDetails);
            return $this->response(
                true,
                'Successful flow details',
                $flowDetailsResponse,
                200
            );

        } else {
            $flowDetails = $response->json();
            return $this->response(
                false,
                'Unable to fetch flow details',
                $flowDetails,
                $response->status()
            );
        }
    }


    /**
     * @OA\Post(
     *     path="/api/whatsapp/{whatsappBusinessAccount}/flows/upload-flow-json",
     *     summary="Upload Flow JSON Asset",
     *     description="Uploads a flow JSON file to the specified flow ID. The file must be a valid JSON and the file name must match the provided name ending with .json.",
     *     operationId="uploadFlowJson",
     *     tags={"WhatsApp Flows"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="flow_id",
     *                     type="string",
     *                     description="The ID of the flow to upload the JSON file to."
     *                 ),
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="The JSON file to upload. The file content must be valid JSON."
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Uploaded Successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Uploaded Successfully"),
     *             @OA\Property(
     *                 property="validations_errors",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/FlowValidationError")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation Error(s) or Upload Failed.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation Error(s)"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="validation_errors",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/FlowValidationError")
     *                 ),
     *                 @OA\Property(
     *                     property="response_body",
     *                     type="string",
     *                     example="File content is not valid JSON."
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function uploadFlowJson(Channel $channel, Request $request): JsonResponse
    {
        $connector = $channel->connector;

        // Retrieve the phone_number_id from WhatsappConfiguration
        $whatsappConfiguration = $connector->whatsappConfiguration;

        $whatsappBusinessAccount = $whatsappConfiguration->whatsappBusinessAccount;

        $accessToken = $whatsappBusinessAccount->name == 'Dreams SMS' ?
            Meta::ACCESS_TOKEN :
            $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);

        if (!$accessToken) {
            return $this->response(false, 'Failed to get a valid access token', null, 401);
        }

        // Validation rules
        $validator = Validator::make($request->all(), [
            'flow_id' => 'required|string',
            'file' => 'required|file|mimes:json',
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }

        $file = $request->file('file');
        $flowId = $request->input('flow_id');

        // Ensure the file content is valid JSON
        $fileContent = file_get_contents($file->getPathname());
        if (json_decode($fileContent) === null && json_last_error() !== JSON_ERROR_NONE) {
            return $this->response(400, 'File content is not valid JSON.');
        }


        $stream = fopen($file->getRealPath(), 'r');

        $response = Http::withToken($accessToken)->attach(
            'file',
            $stream,
            $file->getClientOriginalName()
        )->post("https://graph.facebook.com/v20.0/{$flowId}/assets", [
                    'name' => 'flow.json',
                    'asset_type' => 'FLOW_JSON'
                ]);


        if ($response->successful()) {
            $validationErrors = [
                'validations_errors' => array_map(
                    fn($error) => new FlowValidationError($error),
                    (json_decode($response->body(), true))["validation_errors"]
                )
            ];

            return $this->response(200, 'Uploaded Successfully', $validationErrors);
        } else {

            return $this->response(400, 'Upload JSON File Failed', $response->object());
        }

    }

    /**
     * @OA\Delete(
     *     path="/api/whatsapp/{whatsappBusinessAccount}/flows",
     *     summary="Delete WhatsApp Flow",
     *     operationId="deleteFlow",
     *     tags={"WhatsApp Flows"},
     *     @OA\Parameter(
     *         name="whatsappBusinessAccount",
     *         in="path",
     *         required=true,
     *         description="The ID of the WhatsApp Business Account",
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="flowId",
     *         in="query",
     *         required=true,
     *         description="The ID of the flow to be deleted",
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Flow deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Flow deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Failed to get a valid access token",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Failed to get a valid access token")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Internal Server Error")
     *         )
     *     )
     * )
     */
    public function destroy(Channel $channel, $flowId)
    {
        $connector = $channel->connector;

        // Retrieve the phone_number_id from WhatsappConfiguration
        $whatsappConfiguration = $connector->whatsappConfiguration;

        $whatsappBusinessAccount = $whatsappConfiguration->whatsappBusinessAccount;

        $accessToken = $whatsappBusinessAccount->name == 'Dreams SMS' ?
            Meta::ACCESS_TOKEN :
            $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);

        if (!$accessToken) {
            return $this->response(false, 'Failed to get a valid access token', null, 401);
        }
        // dd($accessToken);
        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $apiVersion = env('FACEBOOK_GRAPH_API_VERSION');
        $url = "https://graph.facebook.com/v20.0/{$flowId}";
        $response = Http::withToken($accessToken)->delete($url);
        if ($response->successful()) {
            return $this->response(true, 'Flow deleted successfully');
        }
        return $this->response(false, json_decode($response->body())->error->message, [], $response->status());
    }

    /**
     * @deprecated
     */
    /**
     * @OA\Post(
     *     path="/whatsapp/{whatsappBusinessAccount}/flows/{flowId}/deprecate",
     *     summary="Deprecate a WhatsApp flow",
     *     description="Deprecate a WhatsApp flow by its ID",
     *     tags={"WhatsApp Flows"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="whatsappBusinessAccount",
     *         in="path",
     *         description="WhatsApp Business Account ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="flowId",
     *         in="path",
     *         description="Flow ID to be deprecated",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Flow deprecated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Flow deprecated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Failed to get a valid access token",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Failed to get a valid access token")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Internal Server Error")
     *         )
     *     )
     * )
     */
    public function deprecate(Channel $channel, $flowId)
    {
        $connector = $channel->connector;

        // Retrieve the phone_number_id from WhatsappConfiguration
        $whatsappConfiguration = $connector->whatsappConfiguration;

        $whatsappBusinessAccount = $whatsappConfiguration->whatsappBusinessAccount;

        $accessToken = $whatsappBusinessAccount->name == 'Dreams SMS' ?
            Meta::ACCESS_TOKEN :
            $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);

        if (!$accessToken) {
            return $this->response(false, 'Failed to get a valid access token', null, 401);
        }
        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $apiVersion = env('FACEBOOK_GRAPH_API_VERSION');
        $url = "https://graph.facebook.com/v20.0/{$flowId}/deprecate";
        $response = Http::withToken($accessToken)->post($url);
        if ($response->successful()) {
            return $this->response(true, 'Flow deprecated successfully');
        }
        return $this->response(false, json_decode($response->body())->error->message, [], $response->status());
    }
}
