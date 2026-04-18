<?php

namespace App\Http\Controllers\Whatsapp;

use App\Constants\Meta;
use App\Enums\LanguageCode;
use App\Enums\TemplateCategory;
use App\Http\Controllers\BaseApiController;
use App\Http\Responses\Template;
use App\Http\Responses\TemplateDetails;
use App\Http\Responses\ValidatorErrorResponse;
use App\Http\Whatsapp\WhatsappTemplatesComponents\AuthenticationComponentFactory;
use App\Http\Whatsapp\WhatsappTemplatesComponents\TemplateComponentFactory;
use App\Models\Channel;
use App\Models\WhatsappBusinessAccount;
use App\Models\WhatsappMessageTemplate;
use App\Traits\BusinessTokenManager;
use App\Traits\WhatsappTemplateManager;
use Http;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class WhatsAppTemplateController extends BaseApiController
{

    use BusinessTokenManager, WhatsappTemplateManager;

    public static function middleware(): array
    {
        return [
            new Middleware('auth:api')
        ];
    }




    /**
     * @throws ConnectionException
     */

    /**
     * @OA\Get(
     *     path="/api/whatsapp/{whatsappBusinessAccount}/message-templates",
     *     tags={"WhatsApp Templates"},
     *     summary="Get all WhatsApp message templates",
     *     description="Retrieve all message templates owned by a WhatsApp Business Account with optional filters.",
     *     @OA\Parameter(
     *         name="whatsappBusinessAccount",
     *         in="path",
     *         required=true,
     *         description="ID of the WhatsApp Business Account",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="The maximum number of templates to return",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filter templates by status",
     *         @OA\Schema(type="string", example="APPROVED")
     *     ),
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         required=false,
     *         description="Filter templates by category",
     *         @OA\Schema(type="string", example="MARKETING")
     *     ),
     *     @OA\Parameter(
     *         name="language",
     *         in="query",
     *         required=false,
     *         description="Filter templates by language code",
     *         @OA\Schema(type="string", example="en")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Templates retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="string", example="1615318605691911"),
     *                 @OA\Property(property="name", type="string", example="dreams"),
     *                 @OA\Property(property="language", type="string", example="en"),
     *                 @OA\Property(property="category", type="string", example="MARKETING"),
     *                 @OA\Property(property="status", type="string", example="APPROVED")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Failed to get a valid access token",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Failed to get a valid access token")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Unable to fetch templates",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Unable to fetch templates")
     *         )
     *     ),
     *     security={{ "apiAuth": {} }}
     * )
     */
    public function getMessageTemplates(Channel $channel, Request $request): JsonResponse
    {
        $connector = $channel->connector;

        // Retrieve the phone_number_id from WhatsappConfiguration
        $whatsappConfiguration = $connector->whatsappConfiguration;

        $whatsappBusinessAccount = $whatsappConfiguration->whatsappBusinessAccount;

        if ($whatsappBusinessAccount->businessManagerAccount->name == 'Dreams Company') {
            $accessToken = Meta::ACCESS_TOKEN;
        } else {
            // Get a valid access token using the trait method
            $accessToken = $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);
        }

        if (!$accessToken) {
            return response()->json(['error' => 'Failed to get a valid access token'], 401);
        }

        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $apiVersion = env('FACEBOOK_GRAPH_API_VERSION');

        // Specify default fields and add any additional filters
        $query = [
            'fields' => 'id,name,language,category,status',
            'limit' => min($request->integer('per_page', 25), 100)
        ];

        if ($request->filled('after')) {
            $query['after'] = $request->get('after');
        }

        // Automatically add any query parameters from the request, e.g., ?category=MARKETING
        // $query = array_merge($query, $request->only(['limit', 'status', 'category', 'language']));
        $query = array_merge(
            $query,
            $request->only(['status', 'category', 'language'])
        );

        $url = "{$baseUrl}/{$apiVersion}/{$whatsappBusinessAccount->id}/message_templates";
        $response = Http::withToken($accessToken)->get($url, $query);

        if (!$response->successful()) {
            return response()->json(['error' => 'Unable to fetch templates'], $response->status());
        }


        $data = $response->json('data');
        $paging = $response->json('paging');
        if (empty($data)) {
            return $this->response(true, 'No templates found', []);
        }


        $templates = collect($data)->map(function ($templateData) {
            return new Template($templateData);
        });


        return $this->response(true, 'Templates retrieved successfully', [
            'data' => $templates,
            'pagination' => [
                'after' => isset($paging['cursors']['after']) ? $paging['cursors']['after'] : null,
                'has_next' => isset($paging['next']),
            ]
        ]);
    }





    /**
     * @throws ConnectionException
     */

    /**
     * @OA\Get(
     *     path="/api/whatsapp/message-templates/{id}",
     *     tags={"WhatsApp Templates"},
     *     summary="Get a specific WhatsApp message template by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Template Information retrieved successfully",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No template found with this ID"
     *     )
     * )
     */
    public function getMessageTemplateById(Channel $channel, string $id): JsonResponse
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
        $templateId = $id;


        $url = "{$baseUrl}/{$apiVersion}/{$templateId}";

        $response = Http::withToken($accessToken)->get($url);

        if (!$response->successful()) {
            return response()->json(['error' => 'Unable to fetch template'], $response->status());
        }

        $data = $response->json();


        if (empty($data)) {
            return $this->response(true, 'No template found with this ID', []);
        }


        $template = new TemplateDetails($data);

        $templateData = $response->json();
        $messageTemplate = WhatsappMessageTemplate::updateOrCreate(
            [
                'id' => $templateData['id'],
                'whatsapp_business_account_id' => $whatsappBusinessAccount->id
            ],
            [
                'name' => $template->getName(),
                'language' => $template->getLanguage(),
                'status' => $template->getStatus(),
                'category' => $template->getCategory(),
            ]
        );

        // Step 2: Validate the components (using the factory) before calling the API
        $category = $template->getCategory();
        $components = $template->components;
        $validatedComponents = $this->validateComponents($components, $category);
        if ($validatedComponents instanceof JsonResponse) {
            return $validatedComponents; // Returns JsonResponse if validation fails
        }


        $this->processComponents($messageTemplate, $validatedComponents, $category);

        return $this->response(true, 'Template Information retrieved successfully', $template);

    }


    // my phone numbers
    /**
     * @OA\Get(
     *     path="/api/whatsapp/phone-numbers",
     *     tags={"WhatsApp Templates"},
     *     summary="Get all WhatsApp phone numbers",
     *     @OA\Response(
     *         response=200,
     *         description="Phone numbers retrieved successfully",
     *         @OA\JsonContent(type="array", @OA\Items(type="string"))
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Unable to fetch phone numbers"
     *     )
     * )
     */
    /**
     * @throws ConnectionException
     */
    public function getWhatsAppPhoneNumbers(): JsonResponse
    {

        $accessToken = Meta::ACCESS_TOKEN;
        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $apiVersion = env('FACEBOOK_GRAPH_API_VERSION');
        $wabaId = env('DREAMS_WHATSAPP_BUSINESS_ACCOUNT_ID');

        $url = "{$baseUrl}/{$apiVersion}/{$wabaId}/phone_numbers";


        $response = Http::withToken($accessToken)->get($url);


        if ($response->successful()) {
            $phoneNumbers = $response->json();


            return response()->json([
                'status' => true,
                'message' => 'Phone numbers retrieved successfully',
                'data' => $phoneNumbers,
            ]);
        } else {

            return response()->json(['error' => 'Unable to fetch phone numbers'], $response->status());
        }
    }


    /**
     * @OA\Post(
     *     path="/api/{whatsappBusinessAccount}/message-templates",
     *     summary="Create a new template",
     *     tags={"WhatsApp Templates"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "category", "language", "components"},
     *             @OA\Property(property="name", type="string", maxLength=512, example="seasonal_promotion"),
     *             @OA\Property(property="language", type="string", example="en_US"),
     *             @OA\Property(property="category", type="string", example="MARKETING"),
     *             @OA\Property(
     *                 property="components",
     *                 type="array",
     *                 @OA\Items(
     *                     required={"type", "text"},
     *                     @OA\Property(property="type", type="string", example="HEADER"),
     *                     @OA\Property(property="text", type="string", example="Our {{1}} is on!"),
     *                     @OA\Property(
     *                         property="example",
     *                         type="object",
     *                         @OA\Property(property="header_text", type="array",
     *                             @OA\Items(
     *                                 type="array",
     *                                 @OA\Items(type="string", example="the end of August"),
     *                                 @OA\Items(type="string", example="25OFF"),
     *                                 @OA\Items(type="string", example="25%")
     *                             )
     *                         ),
     *
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Template created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Template created successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation Error(s) or Facebook API Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation Error(s)"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="errors", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="message", type="string", example="Invalid parameter")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    /*
     * @todo Revise this part
     */
    public function create(Request $request, Channel $channel): JsonResponse
    {
        // Step 1: Validate the request data
        $validationResult = $this->validateTemplateRequest($request, 'create');
        if ($validationResult !== true) {
            return $validationResult; // Returns JsonResponse if validation fails
        }

        /////////////////////////////////////////////////////////////////////////////////////////////////////
        // Step 2: Validate the components (using the factory) before calling the API
        $category = $request->input('category');
        $components = $request->get('components');
        $validatedComponents = $this->validateComponents($components, $category);
        if ($validatedComponents instanceof JsonResponse) {
            return $validatedComponents; // Returns JsonResponse if validation fails
        }


        ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
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

        // Step 4: Send API request to create or update the template
        $templateData = $this->sendTemplateRequest($request, $whatsappBusinessAccount, $accessToken, 'create');

        if ($templateData instanceof JsonResponse && !$templateData->getData(true)['success']) {
            return $templateData;
        }

        // Step 5: Save template data in the database
        $template = WhatsappMessageTemplate::updateOrCreate(
            [
                'id' => $templateData['id'],
                'whatsapp_business_account_id' => $whatsappBusinessAccount->id
            ],
            [
                'name' => $request->input('name'),
                'language' => $request->input('language'),
                'status' => $templateData['status'],
                'category' => $templateData['category'],
            ]
        );

        $this->processComponents($template, $validatedComponents, $category);

        return $this->response(true, 'Template created successfully');


    }


    /**
     * @OA\Post(
     *     path="/api/{whatsappBusinessAccount}/message-templates/{templateId}",
     *     summary="Update a WhatsApp message template",
     *     tags={"WhatsApp Templates"},
     *     description="Edit a WhatsApp message template by changing its category or components. Only templates with an APPROVED, REJECTED, or PAUSED status can be edited.",
     *     @OA\Parameter(
     *         name="whatsappBusinessAccount",
     *         in="path",
     *         required=true,
     *         description="WhatsApp Business Account ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="templateId",
     *         in="path",
     *         required=true,
     *         description="Template ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="category",
     *                 type="string",
     *                 description="New category for the template.",
     *                 example="MARKETING"
     *             ),
     *             @OA\Property(
     *                 property="components",
     *                 type="array",
     *                 description="Array of components to update the template.",
     *                 @OA\Items(
     *                     @OA\Property(
     *                         property="type",
     *                         type="string",
     *                         description="Component type (HEADER, BODY, FOOTER, BUTTONS).",
     *                         example="BODY"
     *                     ),
     *                     @OA\Property(
     *                         property="text",
     *                         type="string",
     *                         description="Text content for BODY, HEADER, or FOOTER.",
     *                         example="Shop now and use code {{1}}."
     *                     ),
     *                     @OA\Property(
     *                         property="example",
     *                         type="object",
     *                         description="Example values for the template placeholders.",
     *                         @OA\Property(
     *                             property="body_text",
     *                             type="array",
     *                             description="Array of placeholder values for body text.",
     *                             @OA\Items(
     *                                 type="string",
     *                                 example="25OFF"
     *                             )
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="buttons",
     *                         type="array",
     *                         description="Button details if type is BUTTONS.",
     *                         @OA\Items(
     *                             @OA\Property(
     *                                 property="type",
     *                                 type="string",
     *                                 description="Button type (QUICK_REPLY, URL, PHONE_NUMBER).",
     *                                 example="QUICK_REPLY"
     *                             ),
     *                             @OA\Property(
     *                                 property="text",
     *                                 type="string",
     *                                 description="Button text.",
     *                                 example="Unsubscribe"
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Template updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="errors",
     *                 type="array",
     *                 @OA\Items(type="string", example="Validation error message")
     *             )
     *         )
     *     )
     * )
     */
    public function update(Request $request, Channel $channel, WhatsappMessageTemplate $whatsappMessageTemplate): JsonResponse
    {
        // Step 1: Validate the request
        $validator = Validator::make($request->all(), [
            'category' => [
                'sometimes',
                'string',
                function ($attribute, $value, $fail) {
                    if (!in_array($value, TemplateCategory::values())) {
                        $fail("The $attribute must be a valid template category.");
                    }
                },
            ],
            'components' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }

        // Step 2: Ensure at least one of 'category' or 'components' is provided
        if (!$request->has('category') && !$request->has('components')) {
            return $this->response(false, 'At least one of "category" or "components" must be provided.', null, 400);
        }

        // Step 3: Validate components if provided
        $validatedComponents = [];
        $category = strtolower($request->input('category') ?? $whatsappMessageTemplate->category);
        if ($request->has('components')) {
            $components = $request->get('components');
            foreach ($components as $component) {
                try {
                    // Use appropriate factory based on the category
                    $validatedComponents[] = ($category === 'authentication')
                        ? AuthenticationComponentFactory::createComponent($component)
                        : TemplateComponentFactory::createComponent($component);

                } catch (InvalidArgumentException $e) {
                    return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse([$e->getMessage()]), 400);
                }
            }
        }

        $connector = $channel->connector;

        // Retrieve the phone_number_id from WhatsappConfiguration
        $whatsappConfiguration = $connector->whatsappConfiguration;

        $whatsappBusinessAccount = $whatsappConfiguration->whatsappBusinessAccount;

        // Step 4: Get the access token
        $accessToken = $whatsappBusinessAccount->name == 'Dreams SMS' ?
            Meta::ACCESS_TOKEN :
            $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);

        if (!$accessToken) {
            return $this->response(false, 'Failed to get a valid access token', null, 401);
        }

        // Step 5: Prepare the API request
        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $apiVersion = env('FACEBOOK_GRAPH_API_VERSION');

        $url = "{$baseUrl}/{$apiVersion}/{$whatsappMessageTemplate->id}";

        $payload = [];
        if ($request->has('category')) {
            $payload['category'] = $request->input('category');
        }
        if ($request->has('components')) {
            $payload['components'] = $request->input('components');
        }

        // Step 6: Send the request to WhatsApp API
        $response = Http::withToken($accessToken)->post($url, $payload);

        if ($response->successful()) {
            // Step 7: Update the template in your local database
            $template = WhatsappMessageTemplate::where('id', $whatsappMessageTemplate->id)->first();
            if ($template) {
                if ($request->has('category')) {
                    $template->category = $request->input('category');
                }
                // Update status if necessary
                $template->status = 'PENDING'; // Update based on your application's logic
                $template->save();

                // Update components if provided
                if ($request->has('components')) {

                    $this->deleteExistingComponents($template->id, $template->category == "AUTHENTICATION" ? strtolower($template->category) : $template->category);
                    // Loop through validated components and use saveComponent
                    foreach ($validatedComponents as $component) {
                        try {
                            // Use saveComponent function to save each component by type
                            $this->saveComponent($template->id, $component, $template->category == "AUTHENTICATION" ? strtolower($template->category) : $template->category);
                        } catch (InvalidArgumentException $e) {
                            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse([$e->getMessage()]), 400);
                        }
                    }
                }
            } else {
                return $this->response(false, 'Template not found.', null, 404);
            }

            return $this->response(true, 'Template updated successfully');
        }

        // Handle API errors
        $error = json_decode($response->body());
        $errorMessage = $error->error->error_user_title . ". " . $error->error->error_user_msg ?? 'An error occurred.';
        return $this->response(false, $errorMessage, [], $response->status());
    }

    /**
     * @OA\Delete(
     *     path="/api/whatsapp/{whatsappBusinessAccount}/message-templates/{templateID}",
     *     summary="Delete a WhatsApp message template",
     *     tags={"WhatsApp Templates"},
     *     description="Delete a WhatsApp message template by specifying its ID.",
     *     @OA\Parameter(
     *         name="whatsappBusinessAccount",
     *         in="path",
     *         required=true,
     *         description="WhatsApp Business Account ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="template",
     *         in="path",
     *         required=true,
     *         description="Template ID to be deleted",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *          name="name",
     *          in="query",
     *          required=true,
     *          description="Template name to be deleted",
     *          @OA\Schema(type="string")
     *      ),
     *     @OA\Response(
     *         response=200,
     *         description="Template deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Failed to get a valid access token"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Unauthorized request"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Internal server error"
     *             )
     *         )
     *     )
     * )
     */

    public function delete(Request $request, Channel $channel, WhatsappMessageTemplate $template): JsonResponse
    {

        $connector = $channel->connector;

        // Retrieve the phone_number_id from WhatsappConfiguration
        $whatsappConfiguration = $connector->whatsappConfiguration;

        $whatsappBusinessAccount = $whatsappConfiguration->whatsappBusinessAccount;
        $accessToken = $whatsappBusinessAccount->businessManagerAccount->name == 'Dreams Company' ?
            Meta::ACCESS_TOKEN :
            $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);

        if (!$accessToken) {
            return $this->response(false, 'Failed to get a valid access token', null, 401);
        }

        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $apiVersion = env('FACEBOOK_GRAPH_API_VERSION');

        // Get the 'name' query parameter from the request
        $name = $request->query('name');

        $url = "{$baseUrl}/{$apiVersion}/{$whatsappBusinessAccount->id}/message_templates?hsm_id={$template->id}&name={$name}";

        $response = Http::withToken($accessToken)->delete($url);

        if ($response->successful()) {
            $template->delete();
            return $this->response(true, 'Template deleted successfully');
        }

        return $this->response(false, json_decode($response->body())->error->message, [], $response->status());
    }


    private function validateTemplateRequest(Request $request, string $mode): mixed
    {
        $uniqueRule = $mode === 'create' ? '|unique:whatsapp_message_templates,name' : '';
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:512|regex:/^[a-z0-9_]+$/' . $uniqueRule,
            'language' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (!LanguageCode::isValidLanguage($value)) {
                        $fail("The $attribute must be a valid language code.");
                    }
                }
            ],
            'category' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (!in_array($value, TemplateCategory::values())) {
                        $fail("The $attribute must be a valid template category.");
                    }
                }
            ],
            'components' => 'required|array',
            'message_send_ttl_seconds' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($request) {
                    $this->validateTtl($attribute, $value, $fail, $request->input('category'));
                }
            ],
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }

        return true;
    }

    private function validateTtl($attribute, $value, $fail, $category)
    {
        if (!in_array($category, ['authentication', 'utility']) && $value !== null) {
            $fail("The $attribute field is only applicable to authentication or utility templates.");
        }
        if ($category === 'authentication' && ($value < 60 || $value > 600) && $value !== -1) {
            $fail('The time to live (TTL) for authentication templates must be between 60 and 600 seconds, or -1 for 30 days.');
        }
        if ($category === 'utility' && ($value < 60 || $value > 3600) && $value !== -1) {
            $fail('The time to live (TTL) for utility templates must be between 60 and 3600 seconds, or -1 for 30 days.');
        }
    }

    // Function to handle the API request for create/update
    private function sendTemplateRequest(Request $request, WhatsappBusinessAccount $account, string $token, string $mode)
    {
        $url = env('FACEBOOK_GRAPH_API_BASE_URL') . '/' . 'v23.0' . '/' . $account->id . '/message_templates';
        $method = $mode === 'create' ? 'post' : 'put';

        $response = Http::withToken($token)->$method($url, $request->all());

        if ($response->successful()) {
            return $response->json();
        }

        return $this->response(false, json_decode($response->body())->error->error_user_msg, [], $response->status());
    }

    // Function to process and save template components
    private function processComponents($template, array $components, string $category): JsonResponse
    {
        foreach ($components as $componentData) {
            try {
                $this->saveComponent($template->id, $componentData, $category);
            } catch (InvalidArgumentException $e) {
                return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse([$e->getMessage()]), 400);
            }
        }

        return $this->response(true, 'Template processed successfully');
    }

    // Function to save components based on type and category
    private function saveComponent(int $templateId, $component, string $category): void
    {
        $componentType = $component->getType();

        if ($category === 'authentication') {
            switch ($componentType) {
                case 'body':
                    $this->saveAuthenticationBodyComponent($templateId, $component);
                    break;
                case 'footer':
                    $this->saveAuthenticationFooterComponent($templateId, $component);
                    break;
                case 'buttons':
                    $this->saveAuthenticationButtonsComponent($templateId, $component);
                    break;
                default:
                    throw new InvalidArgumentException('Unsupported component type for authentication: ' . $componentType);
            }
        } else {
            switch ($componentType) {
                case 'HEADER':
                    $this->saveHeaderComponent($templateId, $component);
                    break;
                case 'BODY':
                    $this->saveBodyComponent($templateId, $component);
                    break;
                case 'FOOTER':
                    $this->saveFooterComponent($templateId, $component);
                    break;
                case 'BUTTONS':
                    $this->saveButtonsComponent($templateId, $component);
                    break;
                default:
                    throw new InvalidArgumentException('Unsupported component type: ' . $componentType);
            }
        }
    }


    private function validateComponents(array $components, string $category): array|JsonResponse
    {
        $validatedComponents = [];

        foreach ($components as $component) {
            try {
                // Use appropriate factory based on the category
                $validatedComponents[] = (strtolower($category) === 'authentication')
                    ? AuthenticationComponentFactory::createComponent($component)
                    : TemplateComponentFactory::createComponent($component);

            } catch (InvalidArgumentException $e) {
                return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse([$e->getMessage()]), 400);
            }
        }

        return $validatedComponents;
    }



}
