<?php

namespace App\Http\Controllers;

use App\Constants\Meta;
use App\Http\Responses\ValidatorErrorResponse;
use App\Jobs\PrepareCampaignMessagesJob;
use App\Jobs\RetryCampaignMessagesJob;
use App\Models\Campaign;
use App\Models\CampaignMessageLog;
use App\Models\Channel;
use App\Models\ContactEntity;
use App\Models\Conversation;
use App\Models\IAMList;
use App\Models\Service;
use App\Models\WhatsappBusinessAccount;
use App\Models\WhatsappMessageTemplate;
use App\Models\WhatsappPhoneNumber;
use App\Models\Workspace;
use App\Traits\BusinessTokenManager;
use App\Traits\WhatsappMessageManager;
use App\Traits\WhatsappTemplateManager;
use App\Traits\WhatsappWalletManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;


class CampaignController extends BaseApiController
{
    use WhatsappTemplateManager, BusinessTokenManager, WhatsappMessageManager, WhatsappWalletManager;

    /**
     * @OA\Post(
     *     path="/api/workspaces/{workspaceId}/campaigns",
     *     summary="Store a newly created campaign.",
     *     operationId="storeCampaign",
     *     tags={"Campaigns"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="workspaceId",
     *         in="path",
     *         required=true,
     *         description="ID of the workspace",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Campaign information",
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", description="Name of the campaign", example="Example Campaign"),
     *             @OA\Property(property="type", type="string", description="Type of the campaign", example="whatsapp"),
     *             @OA\Property(property="whatsapp_message_template_id", type="string", description="Template Id", example="dd479c4d-7ce6-439e-9adc-6d128a1b6e2d"),
     *             @OA\Property(property="send_time_method", type="string", description="Send time method of the campaign", example="NOW"),
     *             @OA\Property(property="list_id", type="array", description="Array of list IDs", items={"type": "string", "example": "dd479c4d-7ce6-439e-9adc-6d128a1b6e2d"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Campaign created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", description="Success message", example="Campaign created successfully"),
     *             @OA\Property(property="campaign", type="object", description="Created campaign object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation Error(s)",
     *         @OA\JsonContent(ref="#/components/schemas/ValidatorErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Workspace or List not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Workspace or List not found")
     *         )
     *     )
     * )
     */
    public function store(Request $request, Workspace $workspace, Channel $channel)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required|string|max:255',
                'type' => 'required|in:whatsapp,sms,email',
                'send_time_method' => 'required|in:NOW,LATER',
                'list_id' => 'required|array', // Updated to accept an array
                'list_id.*' => 'exists:lists,id', // Validate each item in the array
                'whatsapp_message_template_id' => 'required|exists:whatsapp_message_templates,id',
                'time_zone' => 'nullable|string', // Added time zone validation
                'send_time' => 'nullable|date'
            ]
        );


        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }

        $whatsappMessageTemplate = WhatsappMessageTemplate::findOrFail($request->whatsapp_message_template_id);
        $whatsappBusinessAccount = WhatsappBusinessAccount::findOrFail($whatsappMessageTemplate->whatsapp_business_account_id);

        $listIds = $request->list_id;

        // $contacts = IAMList::where('organization_id', '=', $workspace->organization_id)->
        // with(['contacts.identifiers'])
        //     ->whereIn('id', $listIds)
        //     ->get()
        //     ->pluck('contacts')
        //     ->flatten()
        //     ->unique('id');


        $contacts = \DB::table('contacts as c')
            ->join('contact_list as lc', 'lc.contact_id', '=', 'c.id')
            ->leftJoin('identifiers as i', function ($join) {
                $join->on('i.contact_id', '=', 'c.id')
                    ->where('i.key', 'phone-number');
            })
            ->where('c.organization_id', $workspace->organization_id)
            ->whereIn('lc.list_id', $listIds)
            ->select(
                'c.id as contact_id',
                'c.*',
                'i.key as identifier_key',
                'i.value as identifier_value'
            )
            ->orderBy('c.id')
            ->get()
            ->groupBy('contact_id');

        $costCheck = $this->calculateCostForListsV2(
            $workspace,
            $contacts->toArray(),
        );

        if (!$costCheck['success']) {
            return $this->response(false, 'Cost check failed', $costCheck, 400);
        }

        $wallet = $this->getWorkspaceWallet($workspace, Service::firstOrCreate(
            ['name' => \App\Enums\Service::OTHER],
            ['description' => 'whatsapp,hlr']
        )->id);

        if ($wallet->available_amount < $costCheck['total_cost']) {
            return $this->response(false, 'Insufficient wallet balance.', [
                'required' => $costCheck['total_cost'],
                'available' => $wallet->available_amount,
                'currency' => $costCheck['currency']
            ], 402);
        }

        if ($whatsappBusinessAccount->name == 'Dreams SMS') {
            $accessToken = Meta::ACCESS_TOKEN;
        } else {
            $accessToken = $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);
        }
        $template = $this->fetchTemplateFromAPI($request->get('whatsapp_message_template_id'), $accessToken);

        // dd($template);
        if (!$template['success']) {
            return $this->response(false, $template['error'], $template['status']);
        }

        $templateHasVariables = $this->templateHasVariables($template['template']);
        $toSendComponents = [];
        if ($templateHasVariables) {
            // Validate and build components only if the template has variables
            $toSendComponents = $this->validateAndBuildComponents($template['template'], $request->get('components'));
            if (!$toSendComponents['success']) {
                return $this->response(false, $toSendComponents['error'], 400);
            }
        }

        if (isset($toSendComponents['components'])) {
            $templateComponent = $toSendComponents['components'];
        } else {
            $templateComponent = [];
        }
        $headerVariables = [];
        $bodyVariables = [];
        foreach ($request->get('components') as $section) {
            if (isset($section['type'])) {
                if ($section['type'] === 'header') {
                    // For header, collect parameters as numbered variables
                    if (isset($section['parameters'])) {
                        foreach ($section['parameters'] as $index => $param) {
                            if ($param['type'] === 'text') {
                                $headerVariables[] = $param;
                            }
                            if ($param['type'] === 'image') {
                                $headerVariables[] = $param;
                            }
                            if ($param['type'] === 'video') {
                                $headerVariables[] = $param;
                            }
                        }
                    }
                } elseif ($section['type'] === 'body') {
                    // For body, collect parameters as numbered variables
                    if (isset($section['parameters'])) {
                        foreach ($section['parameters'] as $index => $param) {
                            if ($param['type'] === 'text') {
                                $bodyVariables[] = $param;
                            }
                        }
                    }
                }
            }
        }

        $campaign = Campaign::create([
            'name' => $request->input('name'),
            'type' => $request->input('type'),
            'send_time_method' => $request->input('send_time_method'),
            'whatsapp_message_template_id' => $request->input('whatsapp_message_template_id'),
            'workspace_id' => $workspace->id, //
            'send_time' => $request->input('send_time'),
            'whatsapp_phone_number_id' => $channel->whatsappConfiguration->primary_whatsapp_phone_number_id,
            'channel_id' => $channel->id
        ]);

        // Save each list_id in the CampaignList model
        foreach ($request->input('list_id') as $listId) {
            $campaign->campaignLists()->create(['list_id' => $listId]);
        }
        $campaign->setTemplateVariables($headerVariables, $bodyVariables);


        return response()->json([
            'message' => 'Campaign created successfully with cost ' . $costCheck['total_cost'],
            'campaign' => new \App\Http\Responses\Campaign($campaign)
        ], 201);
    }

    /**
     * Display a listing of the campaigns.
     *
     * @return Response
     */
    /**
     * @OA\Get(
     *     path="/api/workspaces/{workspaceId}/campaigns",
     *     summary="List all campaigns in a workspace with pagination",
     *     tags={"Campaigns"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="workspaceId",
     *         in="path",
     *         required=true,
     *         description="ID of the workspace",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of campaigns per page",
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="name",
     *         in="query",
     *         required=false,
     *         description="Filter campaigns by name",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of campaigns",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Campaigns retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Campaign")
     *             ),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="total", type="integer", description="Total number of campaigns"),
     *                 @OA\Property(property="per_page", type="integer", description="Number of campaigns per page"),
     *                 @OA\Property(property="current_page", type="integer", description="Current page number"),
     *                 @OA\Property(property="last_page", type="integer", description="Last page number"),
     *                 @OA\Property(property="from", type="integer", description="Starting campaign index on this page"),
     *                 @OA\Property(property="to", type="integer", description="Ending campaign index on this page")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Workspace not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Workspace not found")
     *         )
     *     )
     * )
     */
    public function index(Request $request, Workspace $workspace, Channel $channel)
    {
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);
        $name = $request->get('name', null);

        $query = Campaign::where('workspace_id', $workspace->id)->where('channel_id', '=', $channel->id);
        if ($name) {
            $query->where('name', 'like', '%' . $name . '%');
        }
        $campaigns = $query->paginate($perPage, ['*'], 'page', $page);
        $response = $campaigns->getCollection()->map(function ($campaign) {
            return new \App\Http\Responses\Campaign($campaign);
        });
        $campaigns->setCollection($response);
        return $this->paginateResponse(true, 'Campaigns retrieved successfully', $campaigns);
    }


    /**
     * @OA\Get(
     *     path="/api/workspaces/{workspaceId}/campaigns/{campaignId}",
     *     summary="Show a campaign",
     *     description="Display a campaign by ID",
     *     operationId="showCampaign",
     *     tags={"Campaigns"},
     *     @OA\Parameter(
     *         name="workspaceId",
     *         in="path",
     *         description="ID of the workspace",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="campaignId",
     *         in="path",
     *         description="ID of the campaign to show",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Campaign found",
     *         @OA\JsonContent(ref="#/components/schemas/Campaign")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Campaign not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Campaign not found")
     *         )
     *     )
     * )
     */
    public function show(Workspace $workspace, Channel $channel, Campaign $campaign)
    {
        return new \App\Http\Responses\Campaign($campaign);
    }

    /**
     * @OA\Patch(
     *     path="/api/workspaces/{workspaceId}/campaigns/{campaignId}",
     *     summary="Update a campaign",
     *     description="Update a campaign by ID",
     *     operationId="updateCampaign",
     *     tags={"Campaigns"},
     *     @OA\Parameter(
     *         name="workspaceId",
     *         in="path",
     *         description="ID of the workspace",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="campaignId",
     *         in="path",
     *         description="ID of the campaign to update",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/Campaign")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Campaign updated",
     *         @OA\JsonContent(ref="#/components/schemas/Campaign")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Campaign not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Campaign not found")
     *         )
     *     )
     * )
     */
    public function update(Request $request, Workspace $workspace, Channel $channel, Campaign $campaign)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required|string|max:255',
                'type' => 'required|in:whatsapp,sms,email',
                'send_time_method' => 'required|in:NOW,LATER',
                'list_id' => 'required|array', // Updated to accept an array
                'list_id.*' => 'exists:lists,id', // Validate each item in the array
                'whatsapp_message_template_id' => 'required|exists:whatsapp_message_templates,id',
                'time_zone' => 'nullable|string', // Added time zone validation
            ]
        );

        if ($validator->fails()) {
            if ($validator->fails()) {
                return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
            }
        }
        $campaign->update([
            'name' => $request->name,
            'type' => $request->type,
            'send_time_method' => $request->send_time_method,
            'whatsapp_message_template_id' => $request->whatsapp_message_template_id,
            'time_zone' => $request->time_zone
        ]);
        $campaign->campaignLists()->delete();
        foreach ($request->list_id as $listId) {
            $campaign->campaignLists()->create(['list_id' => $listId]);
        }
        if ($request->has('components')) {
            $whatsappMessageTemplate = WhatsappMessageTemplate::findOrFail($request->whatsapp_message_template_id);
            $whatsappBusinessAccount = WhatsappBusinessAccount::findOrFail($whatsappMessageTemplate->whatsapp_business_account_id);

            if ($whatsappBusinessAccount->name == 'Dreams SMS') {
                $accessToken = Meta::ACCESS_TOKEN;
            } else {
                $accessToken = $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);
            }
            $template = $this->fetchTemplateFromAPI($request->get('whatsapp_message_template_id'), $accessToken);

            $toSendComponents = $this->validateAndBuildComponents($template['template'], $request->get('components'));
            if (!$toSendComponents['success']) {
                return $this->response(false, $toSendComponents['error'], 400);
            }
            if (isset($toSendComponents['components'])) {
                $templateComponent = $toSendComponents['components'];
            } else {
                $templateComponent = [];
            }
            $headerVariables = [];
            $bodyVariables = [];
            foreach ($request->get('components') as $section) {
                if (isset($section['type'])) {
                    if ($section['type'] === 'header') {
                        // For header, collect parameters as numbered variables
                        foreach ($section['parameters'] as $index => $param) {
                            if ($param['type'] === 'text') {
                                $headerVariables[] = $param;
                            }
                            if ($param['type'] === 'image') {
                                $headerVariables[] = $param;
                            }
                        }
                    } elseif ($section['type'] === 'body') {
                        // For body, collect parameters as numbered variables
                        foreach ($section['parameters'] as $index => $param) {
                            if ($param['type'] === 'text') {
                                $bodyVariables[] = $param;
                            }
                        }
                    }
                }
            }
            $campaign->setTemplateVariables($headerVariables, $bodyVariables);
        }

        return $this->response(true, "Campaign updated successfully", ['campaign' => new \App\Http\Responses\Campaign($campaign)]);
    }

    /**
     * @OA\Delete(
     *     path="/api/workspaces/{workspaceId}/campaigns/{campaign}",
     *     summary="Delete a campaign.",
     *     operationId="deleteCampaign",
     *     tags={"Campaigns"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="workspaceId",
     *         in="path",
     *         required=true,
     *         description="ID of the workspace",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="campaign",
     *         in="path",
     *         required=true,
     *         description="Campaign ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Campaign deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Campaign and its contacts deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Campaign not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Campaign not found")
     *         )
     *     )
     * )
     */
    public function destroy(Workspace $workspace, Channel $channel, Campaign $campaign)
    {
        // Restrict deletion to specific statuses
        if (!in_array($campaign->status, [Campaign::STATUS_DRAFT, Campaign::STATUS_SCHEDULED])) {
            return $this->response(
                false,
                __('messages.campaign_delete_not_allowed'),
                null,
                400
            );
        }
        $campaign->lists()->detach();
        $campaign->delete();
        return $this->response(true, 'Campaign deleted successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/workspaces/{workspaceId}/campaigns/send-test-message",
     *     summary="Send a test message for a campaign",
     *     operationId="sendTestMessage",
     *     tags={"Campaigns"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="workspaceId",
     *         in="path",
     *         required=true,
     *         description="The ID of the workspace",
     *         @OA\Schema(type="string", example="workspace123")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Test message information",
     *         @OA\JsonContent(
     *             required={"from", "id", "to"},
     *             @OA\Property(property="from", type="string", description="ID of the sender's WhatsApp phone number", example="whatsapp123"),
     *             @OA\Property(property="id", type="string", description="Campaign ID", example="dd479c4d-7ce6-439e-9adc-6d128a1b6e2d"),
     *             @OA\Property(property="to", type="string", description="Recipient's contact ID", example="contact456")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Test message sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Test message sent successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation Error(s)",
     *         @OA\JsonContent(ref="#/components/schemas/ValidatorErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Resource not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Phone number not found")
     *         )
     *     )
     * )
     */
    public function sendTestMessage(Request $request, Workspace $workspace, Channel $channel)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|string|exists:campaigns,id',
            'to' => 'required|string|exists:contacts,id'
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }

        $connector = $channel->connector;

        // Retrieve the phone_number_id from WhatsappConfiguration
        $whatsappConfiguration = $connector->whatsappConfiguration;

        if (!$whatsappConfiguration || !$whatsappConfiguration->primary_whatsapp_phone_number_id) {
            return response()->json(['error' =>  __('messages.whatsapp_config_missing')], 400);
        }
        // Add the `from` field to the request data
        $request->merge(['from' => (string) $whatsappConfiguration->primary_whatsapp_phone_number_id]);

        $campaign = Campaign::findOrFail($request->id);
        $contact = ContactEntity::findOrFail($request->to);
        return $this->sendTemplateMessage($campaign, $contact, $request->from);
    }

    private function sendTemplateMessage(Campaign $campaign, ContactEntity $contact, $from)
    {
        // Extract WhatsApp Business Account and determine access token
        $whatsappBusinessAccount = WhatsappBusinessAccount::findOrFail($campaign->whatsappMessageTemplate->whatsapp_business_account_id);

        $accessToken = ($whatsappBusinessAccount->name == 'Dreams SMS')
            ? Meta::ACCESS_TOKEN
            : $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);

        // Fetch recipient's phone number
        $phoneNumber = $contact->identifiers()->where('key', 'phone-number')->value('value');
        if (is_null($phoneNumber)) {
            return $this->response(false, __('messages.phone_not_found'), 404);
        }

        // Fetch and validate the template from API
        $template = $this->fetchTemplateFromAPI($campaign->whatsapp_message_template_id, $accessToken);
        if (!$template['success']) {
            return $this->response(false, $template['error'], $template['status']);
        }

        // Compile and validate template components
        $toSendComponents = [];
        if ($this->templateHasVariables($template['template'])) {
            $compiledComponents = $campaign->compileTemplate($contact);
            $toSendComponents = $this->validateAndBuildComponents($template['template'], $compiledComponents);
            if (!$toSendComponents['success']) {
                return $this->response(false, $toSendComponents['error'], 400);
            }
        }

        // Send the WhatsApp template message
        $response = $this->sendWhatsAppTemplateMessage(
            collect([
                'language' => ['code' => $template['template']['language']],
                'to' => $phoneNumber,
                'from' => $from
            ]),
            $accessToken,
            $toSendComponents['components'] ?? [],
            $template['template']['name']
        );

        if (!$response['success']) {
            return $this->response(false, $response['error'], $response['status']);
        }

        // Save the message and template details in the database
        $whatsappMessageWithRelations = $this->saveTemplateMessageAndComponents(collect([
            'language' => ['code' => 'en'],
            'to' => $phoneNumber,
            'from' => $from,
            'campaign_id' => $campaign->id
        ]), $response['data'], $toSendComponents['components'] ?? null, $template['template']);

        return $this->response(true, 'Template Message Sent Successfully', $whatsappMessageWithRelations, $response['status']);
    }


    /**
     * @OA\Post(
     *     path="/api/workspaces/{workspaceId}/campaigns/{campaignId}/send",
     *     summary="Send a campaign",
     *     description="Dispatches a campaign to send messages to all associated contacts, with optional scheduling.",
     *     operationId="sendCampaign",
     *     tags={"Campaigns"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="workspaceId",
     *         in="path",
     *         required=true,
     *         description="ID of the workspace",
     *         @OA\Schema(type="string", example="2e1e4547-473e-4bf5-a3df-94dda12683aa")
     *     ),
     *     @OA\Parameter(
     *         name="campaignId",
     *         in="path",
     *         required=true,
     *         description="ID of the campaign to be sent",
     *         @OA\Schema(type="string", example="368f2f16-d0f9-4ce4-9291-aa082501b515")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Request payload to initiate or schedule the campaign",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="from",
     *                 type="string",
     *                 description="WhatsApp phone number ID from which messages are sent",
     *                 example="108427225641466"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Campaign sent or scheduled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Campaign scheduled to be sent at 2023-10-30 12:00:00")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error or failure to get access token",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation Error(s)"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 additionalProperties={"type": "array", "items": {"type": "string"}}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Campaign or Workspace not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Workspace not found")
     *         )
     *     )
     * )
     */
    public function send(Workspace $workspace, Channel $channel, Campaign $campaign, Request $request)
    {

        $connector = $channel->connector;

        // Retrieve the phone_number_id from WhatsappConfiguration
        $whatsappConfiguration = $connector->whatsappConfiguration;

        if (!$whatsappConfiguration || !$whatsappConfiguration->primary_whatsapp_phone_number_id) {
            return response()->json(['error' => 'WhatsApp Configuration is missing or incomplete'], 400);
        }
        // Add the `from` field to the request data
        $request->merge(['from' => (string) $whatsappConfiguration->primary_whatsapp_phone_number_id]);

        // Get the 'from' phone number ID
        $fromPhoneNumberId = $request->input('from');

        // Retrieve WhatsApp phone number and access token
        $whatsappPhoneNumber = WhatsappPhoneNumber::whereId($fromPhoneNumberId)->first();
        $whatsappBusinessAccount = $whatsappPhoneNumber->whatsappBusinessAccount;

        $accessToken = $whatsappBusinessAccount->name === 'Dreams SMS'
            ? Meta::ACCESS_TOKEN
            : $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);

        if (!$accessToken) {
            return response()->json(['success' => false, 'message' => 'Failed to get a valid access token'], 400);
        }

        // Fetch template from API
        $response = $this->fetchTemplateFromAPI($campaign->whatsapp_message_template_id, $accessToken);
        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['error']], $response['status']);
        }

        // Mark campaign as in progress
        $campaign->update([
            'status' => Campaign::STATUS_INPROGRESS,
        ]);

        // Dispatch job in queue (does NOT block, no output returned)
        PrepareCampaignMessagesJob::dispatch(
            $campaign->id,
            $fromPhoneNumberId,
            $accessToken,
            $response
        )->onQueue('whatsapp-campaigns');

        // Response is instant
        return response()->json([
            'success' => true,
            'message' => 'Campaign is now in progress.',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/workspaces/{workspaceId}/campaigns/{campaignId}/report",
     *     summary="Get Campaign Report",
     *     description="Fetches a detailed report of contacts associated with a campaign, including message counts and their last status.",
     *     operationId="getCampaignReport",
     *     tags={"Campaigns"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="workspaceId",
     *         in="path",
     *         required=true,
     *         description="ID of the workspace",
     *         @OA\Schema(type="string", example="2e1e4547-473e-4bf5-a3df-94dda12683aa")
     *     ),
     *     @OA\Parameter(
     *         name="campaignId",
     *         in="path",
     *         required=true,
     *         description="ID of the campaign",
     *         @OA\Schema(type="string", example="368f2f16-d0f9-4ce4-9291-aa082501b515")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Campaign report retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="campaign_id", type="string", example="368f2f16-d0f9-4ce4-9291-aa082501b515"),
     *             @OA\Property(property="campaign_name", type="string", example="Winter Promotion"),
     *             @OA\Property(
     *                 property="report",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="contact_id", type="string", example="12345678-1234-5678-1234-567812345678"),
     *                     @OA\Property(property="contact_phone_number", type="string", example="+201126220806"),
     *                     @OA\Property(property="total_messages", type="integer", example=5),
     *                     @OA\Property(property="status", type="string", example="delivered", description="The most recent status of the last message sent")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Campaign or Workspace not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Campaign not found")
     *         )
     *     )
     * )
     */
    public function getCampaignReport(Request $request, Workspace $workspace, Channel $channel, Campaign $campaign)
    {

        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        // 1. Paginate contacts in campaign
        $campaignContacts = $campaign
            ->contactsQuery()
            ->orderBy('contacts.id')
            ->paginate($perPage, ['contacts.*'], 'page', $page);

        $response = $campaignContacts->getCollection()->map(function ($contact) use ($campaign) {
            return new \App\Http\Responses\CampaignContactLog($contact, $campaign->id);
        });
        $campaignContacts->setCollection($response);
        return $this->paginateResponse(true, 'Campaigns retrieved successfully', $campaignContacts);
    }

    /**
     * @OA\Patch(
     *     path="/api/workspaces/{workspaceId}/campaigns/{campaign}/pause",
     *     summary="Pause a campaign",
     *     operationId="pauseCampaign",
     *     tags={"Campaigns"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="workspaceId",
     *         in="path",
     *         required=true,
     *         description="ID of the workspace",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="campaign",
     *         in="path",
     *         required=true,
     *         description="ID of the campaign to pause",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Campaign paused successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Campaign paused successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Campaign cannot be paused")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Campaign not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Campaign not found")
     *         )
     *     )
     * )
     */
    public function pause(Workspace $workspace, Campaign $campaign)
    {
        if ($campaign->status !== Campaign::STATUS_ACTIVE) {
            return response()->json(['success' => false, 'message' => 'Campaign can only be paused if active.'], 400);
        }

        $campaign->update(['status' => Campaign::STATUS_PAUSED]);
        return response()->json(['success' => true, 'message' => 'Campaign paused successfully']);
    }

    /**
     * @OA\Patch(
     *     path="/api/workspaces/{workspaceId}/campaigns/{campaign}/cancel",
     *     summary="Cancel a campaign",
     *     operationId="cancelCampaign",
     *     tags={"Campaigns"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="workspaceId",
     *         in="path",
     *         required=true,
     *         description="ID of the workspace",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="campaign",
     *         in="path",
     *         required=true,
     *         description="ID of the campaign to cancel",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Campaign canceled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Campaign canceled successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Campaign cannot be canceled")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Campaign not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Campaign not found")
     *         )
     *     )
     * )
     */
    public function cancel(Workspace $workspace, Campaign $campaign)
    {
        if (!in_array($campaign->status, [Campaign::STATUS_DRAFT, Campaign::STATUS_SCHEDULED, Campaign::STATUS_ACTIVE])) {
            return response()->json(['success' => false, 'message' => 'Only draft, scheduled, or active campaigns can be canceled.'], 400);
        }

        $campaign->update(['status' => Campaign::STATUS_CANCELLED]);
        return response()->json(['success' => true, 'message' => 'Campaign canceled successfully']);
    }


    /**
     * @OA\Patch(
     *     path="/api/workspaces/{workspaceId}/campaigns/{campaign}/activate",
     *     summary="Activate a campaign",
     *     operationId="activateCampaign",
     *     tags={"Campaigns"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="workspaceId",
     *         in="path",
     *         required=true,
     *         description="ID of the workspace",
     *         @OA\Schema(type="string", example="workspace123")
     *     ),
     *     @OA\Parameter(
     *         name="campaign",
     *         in="path",
     *         required=true,
     *         description="ID of the campaign to activate",
     *         @OA\Schema(type="string", example="campaign456")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Campaign activated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Campaign activated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Only scheduled or paused campaigns can be activated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Campaign not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Campaign not found")
     *         )
     *     )
     * )
     */

    public function activate(Workspace $workspace, Campaign $campaign)
    {
        // Only allow activation if the campaign is scheduled or paused
        if (!in_array($campaign->status, [Campaign::STATUS_SCHEDULED, Campaign::STATUS_PAUSED])) {
            return response()->json([
                'success' => false,
                'message' => 'Only scheduled or paused campaigns can be activated.'
            ], 400);
        }

        // Update the campaign status to active
        $campaign->update(['status' => Campaign::STATUS_ACTIVE]);

        //        // Optionally, dispatch a job to start sending messages
        //        PrepareCampaignMessagesJob::dispatch($campaign->id);

        return response()->json(['success' => true, 'message' => 'Campaign activated successfully']);
    }

    public function getCampaignLogAttempts(Request $request, Workspace $workspace, Channel $channel, Campaign $campaign, CampaignMessageLog $log)
    {

        // Transform each attempt
        $attempts = $log->attempts()->get()->map(function ($attempt) {
            return new \App\Http\Responses\CampaignMessageAttempt($attempt);
        });

        return $this->response(true, 'Campaign Message Log Attempts Retrieved Successfully', $attempts);
    }

    public function retryFailedOrUnsent(Request $request, Workspace $workspace, Channel $channel, Campaign $campaign)
    {

        // Retrieve the phone_number_id from WhatsappConfiguration
        $whatsappConfiguration = $channel->whatsappConfiguration;
        $fromPhoneNumberId = $whatsappConfiguration->primary_whatsapp_phone_number_id;

        if (!$whatsappConfiguration || !$fromPhoneNumberId) {
            return response()->json(['error' => 'WhatsApp Configuration is missing or incomplete'], 400);
        }
        // Add the `from` field to the request data
        $request->merge(['from' => (string) $fromPhoneNumberId]);


        $whatsappBusinessAccount = $whatsappConfiguration->whatsappBusinessAccount;

        $accessToken = $whatsappBusinessAccount->name === 'Dreams SMS'
            ? Meta::ACCESS_TOKEN
            : $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);

        if (!$accessToken) {
            return response()->json(['success' => false, 'message' => 'Failed to get a valid access token'], 400);
        }

        // Fetch template from API
        $response = $this->fetchTemplateFromAPI($campaign->whatsapp_message_template_id, $accessToken);
        if (!$response['success']) {
            return response()->json(['success' => false, 'message' => $response['error']], $response['status']);
        }

        // Mark campaign as in progress
        $campaign->update([
            'status' => Campaign::STATUS_INPROGRESS,
        ]);

        // Dispatch job in queue (does NOT block, no output returned)
        RetryCampaignMessagesJob::dispatch(
            $campaign->id,
            $fromPhoneNumberId,
            $accessToken,
            $response
        )->onQueue('whatsapp-campaigns');

        // Response is instant
        return response()->json([
            'success' => true,
            'message' => 'Campaign Retry is now in progress.',
        ]);
    }


    /**
     * Resend failed WhatsApp template messages.
     * This creates new sending attempts with proper billing/deduction.
     *
     * @param Request $request
     * @param Workspace $workspace
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendFailedMessages(Request $request, Workspace $workspace)
    {
        $validator = Validator::make($request->all(), [
            'message_ids' => 'required|array|min:1',
            'message_ids.*' => 'required|string|exists:whatsapp_messages,id',
        ]);
        if ($validator->fails()) {
            return $this->response(
                false,
                'Validation Error(s)',
                new ValidatorErrorResponse($validator->errors()->toArray()),
                400
            );
        }

        $messageIds = $request->input('message_ids');

        // Get failed template messages grouped by campaign
        $messages = \App\Models\WhatsappMessage::with('campaign.channel.whatsappConfiguration.whatsappBusinessAccount')
            ->whereIn('id', $messageIds)
            ->where('type', \App\Models\WhatsappMessage::MESSAGE_TYPE_TEMPLATE)
            ->where('status', \App\Models\WhatsappMessage::MESSAGE_STATUS_FAILED)
            ->where('messageable_type', \App\Models\WhatsappTemplateMessage::class)
            ->whereHas('campaign', function ($query) use ($workspace) {
                $query->where('workspace_id', $workspace->id);
            })
            ->get();

        if ($messages->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No valid failed template messages found for this workspace',
            ], 404);
        }

        if ($messages->count() !== \count($messageIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Some message IDs are either not failed templates, do not exist, or do not belong to this workspace',
            ], 400);
        }

        // Update message status to 'initiated' immediately to prevent duplicate requests
        \App\Models\WhatsappMessage::whereIn('id', $messageIds)
            ->where('status', \App\Models\WhatsappMessage::MESSAGE_STATUS_FAILED)
            ->update(['status' => \App\Models\WhatsappMessage::MESSAGE_STATUS_INITIATED]);
        // Group messages by campaign
        $messagesByCampaign = $messages->groupBy('campaign_id');

        $campaignsProcessed = 0;
        $messagesProcessed = 0;

        // Process each campaign separately
        foreach ($messagesByCampaign as $campaignId => $campaignMessages) {
            $campaign = $campaignMessages->first()->campaign;
            $channel = $campaign->channel;

            if (!$channel || !$channel->whatsappConfiguration) {
                Log::warning("Campaign {$campaignId} has no valid channel configuration");
                continue;
            }

            $whatsappConfiguration = $channel->whatsappConfiguration;
            $fromPhoneNumberId = $whatsappConfiguration->primary_whatsapp_phone_number_id;

            if (!$fromPhoneNumberId) {
                Log::warning("Campaign {$campaignId} has no phone number configured");
                continue;
            }

            $whatsappBusinessAccount = $whatsappConfiguration->whatsappBusinessAccount;

            $accessToken = $whatsappBusinessAccount->name === 'Dreams SMS'
                ? Meta::ACCESS_TOKEN
                : $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);

            if (!$accessToken) {
                Log::warning("Campaign {$campaignId} failed to get access token");
                continue;
            }

            // Fetch template from API
            $response = $this->fetchTemplateFromAPI($campaign->whatsapp_message_template_id, $accessToken);
            if (!$response['success']) {
                Log::warning("Campaign {$campaignId} failed to fetch template: " . $response['error']);
                continue;
            }

            // Get message IDs for this campaign
            $campaignMessageIds = $campaignMessages->pluck('id')->toArray();

            // Dispatch job to resend messages
            \App\Jobs\ResendFailedWhatsappMessagesJob::dispatch(
                $campaign->id,
                $campaignMessageIds,
                $fromPhoneNumberId,
                $accessToken,
                $response
            )->onQueue('whatsapp-campaigns');

            $campaignsProcessed++;
            $messagesProcessed += \count($campaignMessageIds);
        }

        return response()->json([
            'success' => true,
            'message' => 'Messages are being resent. Billing will be applied for each sent message.',
            'campaigns_count' => $campaignsProcessed,
            'messages_count' => $messagesProcessed,
        ]);
    }
}
