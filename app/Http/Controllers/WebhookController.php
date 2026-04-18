<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\BaseApiController;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\Organization;
use App\Models\Webhook;
use App\Models\WebhookService;
use App\Models\WebhookEvent;
use Response;

class WebhookController extends BaseApiController implements HasMiddleware
{


    public static function middleware(): array
    {
        return [
            new Middleware('auth:api')
        ];
    }

    public function __construct()
    {
    }
    /**
     * @OA\Get(
     *     path="/api/organizations/{organization}/webhooks",
     *     summary="View user webhook",
     *     tags={"Webhook"},
     *     @OA\Parameter(
     *         name="user_id",
     *         in="path",
     *         required=true,
     *         description="ID of the user",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="UserWebhook"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="user_id", type="integer"),
     *                     @OA\Property(property="webhook_url", type="string"),
     *                     @OA\Property(property="date", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     )
     * )
     */
    public function index(Request $request, Organization $organization)
    {
        $perPage = $request->get('per_page', 15); // Default to 15 if not provided
        $page = $request->get('page', 1);
        $query = Webhook::where('organization_id',$organization->id);
        $webhooks = $query->paginate($perPage, ['*'], 'page', $page);
        $response = $webhooks->getCollection()->map(function ($webhook) {
            return new \App\Http\Responses\Webhook($webhook);
        });
        $webhooks->setCollection($response);
        return $this->paginateResponse(true, 'Webhooks retrieved successfully', $webhooks);

    }

    /**
     * @OA\Get(
     *     path="/api/organizations/{organization}/webhooks/{webhook}",
     *     summary="Show specific user webhook",
     *     tags={"Webhook"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the user webhook",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="UserWebhook"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="user_id", type="integer"),
     *                 @OA\Property(property="webhook_url", type="string"),
     *                 @OA\Property(property="date", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User webhook not found"
     *     )
     * )
     */
    public function show(Organization $organization, Webhook $webhook)  
    {
        if ($webhook->organization_id !== $organization->id) {
            return $this->response(false, 'errors', ['webhook' => 'Unauthorized access'], 403);
        }
        return $this->response(true, 'UserWebhook', new \App\Http\Responses\Webhook($webhook));
    }

    /**
     * @OA\Post(
     *     path="/api/organizations/{organization}/webhooks/",
     *     summary="Create a new user webhook",
     *     tags={"Webhook"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"webhook_url"},
     *             @OA\Property(property="webhook_url", type="string", minLength=5, maxLength=500, example="https://example.com/webhook")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="UserWebhook"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="user_id", type="integer"),
     *                 @OA\Property(property="webhook_url", type="string"),
     *                 @OA\Property(property="date", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="errors"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function store(Request $request, Organization $organization)
    {
        //required|
        $validator = Validator::make($request->all(), [
            'url' => 'required|url|min:5|max:500',
            'service_id' => 'required|exists:webhook_services,id',
            'event_id' => [
                    'required',
                    'exists:webhook_events,id',
                    Rule::unique('webhooks')->where(function ($query) use ($organization) {
                        return $query->where('organization_id', $organization->id);
                    })
                ],
            'channel_id' => 'nullable|exists:channels,id'

        ]);
        if ($validator->fails()) {
            return $this->response(false, 'errors', $validator->errors(), 400);
        }
        $data = $request->all();
        $data['organization_id'] = $organization->id;
        $data['signing_key'] = \Str::uuid();
        $webhook = Webhook::create($data);
        return $this->response(true, 'UserWebhook', $webhook);
    }


    /**
     * @OA\Put(
     *     path="/api/organizations/{organization}/webhooks/{webhook}",
     *     summary="Update a user webhook",
     *     tags={"Webhooks"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the user webhook to update",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="webhook_url", type="string", minLength=5, maxLength=500, example="https://example.com/updated-webhook")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="UserWebhook"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="user_id", type="integer"),
     *                 @OA\Property(property="webhook_url", type="string"),
     *                 @OA\Property(property="date", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="errors"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User webhook not found"
     *     )
     * )
     */

    public function update(Request $request, Organization $organization, Webhook $webhook)
    {

        if ($webhook->organization_id !== $organization->id) {
            return $this->response(false, 'errors', ['webhook' => 'Unauthorized access'], 403);
        }

        // Validate the request
        $validator = Validator::make($request->all(), [
            'url' => 'required|url|min:5|max:500',
            'service_id' => 'required|exists:webhook_services,id',
            'event_id' => [
                    'required',
                    'exists:webhook_events,id',
                    Rule::unique('webhooks')->where(function ($query) use ($organization) {
                        return $query->where('organization_id', $organization->id);
                    })->ignore($webhook->id)  // Ignore current webhook when checking uniqueness
                ],
            'channel_id' => 'nullable|exists:channels,id'
        ]);
        if ($validator->fails()) {
            return $this->response(false, 'errors', $validator->errors(), 400);
        }

        $data = $request->all();
        $webhook = $webhook->update($data);
        return $this->response(true, 'UserWebhook', $webhook);
    }

    /**
     * @OA\Delete(
     *     path="/api/organizations/{organization}/webhooks/{webhook}",
     *     operationId="deleteUserWebhook",
     *     tags={"Webhooks"},
     *     summary="Delete a user webhook",
     *     description="Deletes a specific user webhook by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the user webhook to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Row Deleted")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User webhook not found"
     *     )
     * )
     */

    public function destroy(Organization $organization, Webhook $webhook)
    {
        if ($webhook->organization_id !== $organization->id) {
            return $this->response(false, 'errors', ['webhook' => 'Unauthorized access'], 403);
        }

        Webhook::destroy($webhook->id);
        return $this->response(true, 'Row Deleted');
    }

     /**
     * @OA\Get(
     *     path="/api/organizations/{organization}/webhooks/services",
     *     summary="Get all webhook services",
     *     tags={"Webhook"},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         required=true,
     *         description="ID of the organization",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="services"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function services(Organization $organization){
        return $this->response(true,'services', WebhookService::all());
    }

    /**
     * @OA\Get(
     *     path="/api/organizations/{organization}/webhooks/events",
     *     summary="Get webhook events for a specific service",
     *     tags={"Webhook"},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         required=true,
     *         description="ID of the organization",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="service_id",
     *         in="query",
     *         required=true,
     *         description="ID of the webhook service",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="events"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="webhook_service_id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="is_active", type="boolean"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="errors"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="service_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The service id field is required.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function events(Organization $organization,Request $request){
        $validator = Validator::make($request->all(), [
            'service_id' => 'required|exists:webhook_services,id'
        ]);
        if ($validator->fails()) {
            return $this->response(false, 'errors', $validator->errors(), 400);
        }
        
        return $this->response(true, 'events', WebhookEvent::Active()->where('webhook_service_id', $request->service_id)->get());
    }
}
