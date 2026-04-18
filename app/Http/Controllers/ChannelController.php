<?php

namespace App\Http\Controllers;

use App\Constants\Meta;
use App\Http\Responses\BusinessManagerAccount;
use App\Http\Responses\ValidatorErrorResponse;
use App\Http\Responses\WhatsAppBusinessAccount;
use App\Http\Responses\WhatsappBusinessProfile;
use App\Http\Responses\WhatsappPhoneNumber;
use App\Models\Channel;
use App\Models\Connector;
use App\Models\Organization;
use App\Models\WhatsappConfiguration;
use App\Models\SmsConfiguration;
use App\Models\Workspace;
use DB;
use Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;
use Validator;
use App\Http\Controllers\SmsUsers\SendersController;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Traits\ChannelManager;
use Exception;
use App\Traits\WalletManager;
use App\Models\Service as MService;
use App\Enums\Service as EnumService;
use App\Models\Gateway;
use App\Models\GatewaySender;

class ChannelController extends BaseApiController
{
    use AuthorizesRequests, ChannelManager;
    use WalletManager;

    public function index(Request $request): JsonResponse
    {

        // Get pagination parameters
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);
        // Optional platform filter, specifically checking for WhatsApp
        $platform = $request->query('platform');
        $status = $request->query('status');
        $query = Channel::query();

        if ($platform === Channel::WHATSAPP_PLATFORM) {
            $query->with(['connector.whatsappConfiguration.whatsappBusinessAccount.whatsappPhoneNumbers']);
            $query->where('channels.platform', Channel::WHATSAPP_PLATFORM);
        }

        if ($platform === Channel::SMS_PLATFORM) {
            $query->with(['connector.smsConfiguration.sender']);
            $query->where('channels.platform', Channel::SMS_PLATFORM);
        }

        if ($status) {
            $query->where('status', $status);
        }
        // Fetch channels based on the query (filtered or all)
        $channels = $query->orderBy('id', 'desc')->paginate($perPage, ['*'], 'page', $page);
        $channelsResponse = $channels->map(function ($channel) {
            return new \App\Http\Responses\Channel($channel);
        });

        return $this->response(true, 'Channels retrieved', $channelsResponse);
    }

    public function show(Channel $channel): JsonResponse
    {
        if ($channel->platform === Channel::WHATSAPP_PLATFORM) {
            $channel->load(['connector.whatsappConfiguration.whatsappBusinessAccount.whatsappPhoneNumbers']);
        }

        if ($channel->platform === Channel::SMS_PLATFORM) {
            $channel->load(
                \Auth::guard('admin')->check()
                    ? ['connector.smsConfiguration.sender.gateways']
                    : ['connector.smsConfiguration.sender']
            );
        }

        return $this->response(true, 'Channel retrieved', new \App\Http\Responses\Channel($channel));
    }

    public function update(Channel $channel, Request $request): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'string',
            ]
        );

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }

        $validatedData = $validator->validated();

        // Wrap the main logic in a transaction
        $requestall = $request->all();

        return DB::transaction(function () use ($validatedData, $requestall, $channel) {
            try {
                if ($channel->platform === Channel::SMS_PLATFORM) {

                    $senderResponse = $this->updateSmsSender($requestall, $channel->connector?->SmsConfiguration?->Sender->id);
                    if (!$senderResponse['success']) {
                        return $this->response(false, 'Validation Error(s)', $senderResponse['validation_errors'], 400);
                    }
                    if (!empty($requestall['gateway_id'])) {
                        $gatewaySender = GatewaySender::firstOrCreate(
                            ['sender_id' => $channel->connector?->SmsConfiguration?->Sender->id], // Find by sender_id
                            ['gateway_id' => $requestall['gateway_id']] // If not found, insert with these values
                        );
                    }

                    return $this->response(true, 'Channel updated successfully', [
                        'channel' => $channel,
                    ]);
                }
            } catch (Exception $e) {
                throw new Exception('Error during Connector creation: ' . $e->getMessage());
            }
        });
    }

    private function updateSmsSender(array $data, int $id)
    {
        try {

            $senderController = app(SendersController::class);
            $senderResponse = $senderController->update(new Request($data), $id); // Pass the request data

            // Parse the response
            $responseData = $senderResponse->getData(true); // Parse JSON response

            // Check for validation errors
            if ($senderResponse->getStatusCode() !== 200 || !$responseData['success']) {
                if (isset($responseData['data'])) {
                    // Return validation errors
                    return [
                        'success' => false,
                        'validation_errors' => $responseData['data'], // Adjust if needed
                    ];
                }

                throw new Exception($responseData['message'] ?? 'An unexpected error occurred.');
            }

            // Return the sender data on success
            return [
                'success' => true,
                'sender' => $responseData['data'], // Full sender data
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed To Create Sender SMS: ' . $e->getMessage(),
            ];
        }
    }

    public function approve(Channel $channel)
    {
        if ($channel->platform !== Channel::SMS_PLATFORM) {
            return $this->response(false, 'Channel does not belong to the specified platform.', null, 403);
        }
        try {
            DB::transaction(function () use ($channel) {
                $smsConfig = SmsConfiguration::where('connector_id', $channel->connector_id)->first();
                if ($smsConfig) {
                    $smsConfig->update(['status' => SmsConfiguration::STATUS_ACTIVE]);
                }
                $channel->update(['status' => Channel::STATUS_ACTIVE]);
                $this->handleApproveSmsChannel($smsConfig->sender_id);
            });

            return $this->response(true, 'Channel approved successfully', null);
        } catch (Exception $e) {
            return $this->response(false, 'An error occurred while approve the channel.', 500);
        }
    }

    public function reject(Channel $channel)
    {
        if ($channel->platform !== Channel::SMS_PLATFORM) {
            return $this->response(false, 'Channel does not belong to the specified platform.', null, 403);
        }
        try {
            DB::transaction(function () use ($channel) {
                $smsConfig = SmsConfiguration::where('connector_id', $channel->connector_id)->first();
                if ($smsConfig) {
                    $smsConfig->update(['status' => SmsConfiguration::STATUS_INACTIVE]);
                }
                $channel->update(['status' => Channel::STATUS_INACTIVE]);
                $this->handleRejectSmsChannel($smsConfig->sender_id);
            });

            return $this->response(true, 'Channel approved successfully', null);
        } catch (Exception $e) {
            return $this->response(false, 'An error occurred while reject the channel.', 500);
        }
    }

    public function markAsWaitingPayment(Channel $channel)
    {
        if ($channel->platform !== Channel::SMS_PLATFORM) {
            return $this->response(false, 'Channel does not belong to the specified platform.', null, 403);
        }

        try {
            DB::transaction(function () use ($channel) {
                $smsConfig = SmsConfiguration::where('connector_id', $channel->connector_id)->first();
                if ($smsConfig) {
                    $smsConfig->update(['status' => SmsConfiguration::STATUS_PENDING]);
                }
                $channel->update(['status' => Channel::STATUS_PENDING]);
                $this->hanedlsWaitingPaymentSmsChannel($smsConfig->sender_id);
            });

            return $this->response(true, 'Channel approved successfully', null);
        } catch (Exception $e) {
            return $this->response(false, 'An error occurred while reject the channel.', 500);
        }
    }

    public function activateChannelWithPayment(Request $request, Organization $organization, Channel $channel): JsonResponse
    {
        if ($channel->platform !== Channel::SMS_PLATFORM) {
            return $this->response(false, 'Channel does not belong to the specified platform.', null, 403);
        }
        // Check if the channel is already active
        if ($channel->status === Channel::STATUS_ACTIVE) {
            return $this->response(false, 'Channel is already active.', null, 400);
        }

        try {
            return DB::transaction(function () use ($channel, $organization) {
                $paymentSuccessful = $this->processPayment($organization, 200);
                if (!$paymentSuccessful) {
                    return $this->response(false, 'Payment failed. Please try again.', null, 500);
                }
                // return $this->response(true, 'Channel approved successfully', null);

                $smsConfig = SmsConfiguration::where('connector_id', $channel->connector_id)->first();
                if ($smsConfig) {
                    $smsConfig->update(['status' => SmsConfiguration::STATUS_ACTIVE]);
                }
                $channel->update(['status' => Channel::STATUS_ACTIVE]);
                $this->handleApproveSmsChannel($smsConfig->sender_id);


                return $this->response(true, 'Channel activated successfully.', new \App\Http\Responses\Channel($channel));
            });
        } catch (Exception $e) {
            return $this->response(false, 'An error occurred while approve the channel.', 500);
        }

        // Process the payment (this is a placeholder, replace with actual payment processing logic)



    }

    private function processPayment(Organization $organizaion, float $amount)
    {
        $wallet = $this->getObjectWallet($organizaion, MService::where('name', EnumService::OTHER)->value('id'));
        if (!$wallet) {
            return false;
        }
        if (!$this->changeBalance($wallet, $amount, EnumService::OTHER, "Activate sender name")) {
            return false;
        }

        return true;
    }

    protected function handleApproveSmsChannel($sender_id)
    {
        $senderController = app(\App\Http\Controllers\Admin\SenderController::class);
        $senderResponse = $senderController->enable($sender_id); // Pass the request data
        $responseData = $senderResponse->getData(true); // Parse JSON response
        if ($senderResponse->getStatusCode() !== 200 || !$responseData['success']) {
            if (isset($responseData['data'])) {
                return [
                    'success' => false,
                    'validation_errors' => $responseData['data'], // Adjust if needed
                ];
            }

            throw new Exception($responseData['message'] ?? 'An unexpected error occurred.');
        }
    }

    protected function handleRejectSmsChannel($sender_id)
    {
        $senderController = app(\App\Http\Controllers\Admin\SenderController::class);
        $senderResponse = $senderController->disable($sender_id); // Pass the request data
        $responseData = $senderResponse->getData(true); // Parse JSON response
        if ($senderResponse->getStatusCode() !== 200 || !$responseData['success']) {
            if (isset($responseData['data'])) {
                return [
                    'success' => false,
                    'validation_errors' => $responseData['data'], // Adjust if needed
                ];
            }

            throw new Exception($responseData['message'] ?? 'An unexpected error occurred.');
        }
    }

    protected function hanedlsWaitingPaymentSmsChannel($sender_id)
    {
        $senderController = app(\App\Http\Controllers\Admin\SenderController::class);
        $senderResponse = $senderController->markAsWaitingPayment($sender_id); // Pass the request data
        $responseData = $senderResponse->getData(true); // Parse JSON response
        if ($senderResponse->getStatusCode() !== 200 || !$responseData['success']) {
            if (isset($responseData['data'])) {
                return [
                    'success' => false,
                    'validation_errors' => $responseData['data'], // Adjust if needed
                ];
            }

            throw new Exception($responseData['message'] ?? 'An unexpected error occurred.');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/channels/{workspaceId}/available",
     *     summary="Get available channels",
     *     description="Fetch the list of available channels and indicate whether each is installed.",
     *     operationId="getAvailableChannels",
     *     tags={"Channels"},
     *     @OA\Parameter(
     *         name="workspaceId",
     *         in="path",
     *         description="The ID of the workspace",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             example="d587ce39-6be2-48c0-b400-5d776464a112"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of available channels with installation status",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Available Channels"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="name", type="string", example="Whatsapp"),
     *                     @OA\Property(property="platform", type="string", example="whatsapp"),
     *                     @OA\Property(property="icon", type="string", example="url-to-whatsapp-icon"),
     *                     @OA\Property(property="status", type="string", example="Install")
     *                 )
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

    public function getAvailableChannels($workspace_id)
    {


        // Verify that the workspace exists
        $workspace = Workspace::find($workspace_id);
        if (!$workspace) {
            return $this->response(false, 'Workspace not found', null, 404);
        }
        // List of all possible channels (in real applications, you might fetch these from a config or database)
        $availableChannels = [
            [
                'name' => 'Whatsapp',
                'platform' => 'whatsapp',
                'icon' => 'url-to-whatsapp-icon',
            ],
            [
                'name' => 'SMS',
                'platform' => 'sms',
                'icon' => 'url-to-sms-icon',
            ],

        ];

        // Fetch installed channels for the workspace using the relationship
        $installedChannels = $workspace->channels()
            ->pluck('platform')
            ->toArray();

        // Mark each available channel as installed or not
        foreach ($availableChannels as &$channel) {
            $channel['status'] = in_array($channel['platform'], $installedChannels) ? 'Installed' : 'Install';
        }

        return $this->response(true, 'Available Channels', $availableChannels);
    }

    /**
     * @OA\Get(
     *     path="/api/channels/whatsapp/setup-info",
     *     summary="Get WhatsApp setup information",
     *     description="Fetch detailed information required to set up WhatsApp as a channel.",
     *     operationId="getWhatsappSetupInfo",
     *     tags={"Channels"},
     *     @OA\Response(
     *         response=200,
     *         description="WhatsApp setup information",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="WhatsApp Setup Information"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="preInstallation", type="string"),
     *                 @OA\Property(property="preInstallationList", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="installation", type="string"),
     *                 @OA\Property(property="installationList", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="overviewListImages", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="overview", type="string"),
     *                 @OA\Property(property="WhatCanWithWhatsApp", type="string"),
     *                 @OA\Property(property="useWhatsAppToList", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="capabilitiesText", type="string"),
     *                 @OA\Property(property="capabilitiesTableOne", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="type", type="string"),
     *                         @OA\Property(property="send", type="string"),
     *                         @OA\Property(property="receive", type="string"),
     *                     ),
     *                 ),
     *                 @OA\Property(property="capabilitiesTableTwo", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="type", type="string"),
     *                         @OA\Property(property="send", type="string"),
     *                         @OA\Property(property="receive", type="string"),
     *                     ),
     *                 ),
     *                 @OA\Property(property="capabilitiesTableThree", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="type", type="string"),
     *                         @OA\Property(property="send", type="string"),
     *                         @OA\Property(property="receive", type="string"),
     *                     ),
     *                 ),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Channel not found or unsupported",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Channel not found")
     *         )
     *     )
     * )
     */
    public function getWhatsappSetupInfo(): JsonResponse
    {
        $whatsappSetupInfo = [
            'preInstallation' => 'We highly recommend reading our WhatsApp onboarding guide to ensure a smooth installation before proceeding to the next steps. To install WhatsApp, make sure you have the following:',
            'preInstallationList' => [
                'Access to Facebook Business Manager',
                'A viable phone number for WhatsApp: this can be either your own phone number or a phone number that you\'ve purchased from Numbers by Bird.',
                'Your business\' legal address and details',
            ],
            'installation' => 'We\'ll guide you through the installation process, ensuring that your business and your number both meet the WhatsApp criteria and verifying your Facebook Business account and WhatsApp for Business number.',
            'installationList' => [],
            'overviewListImages' => [
                'https://app.bird.com/channels/wa/wa-1.png',
                'https://app.bird.com/channels/wa/wa-2.png',
                'https://app.bird.com/channels/wa/wa-3.png',
                'https://app.bird.com/channels/wa/wa-4.png',
                'https://app.bird.com/channels/wa/wa-5.png',
                'https://app.bird.com/channels/wa/wa-6.png',
            ],
            'overview' => 'WhatsApp is an incredibly popular messaging channel around the world, with over 2 billion users in 180 countries and 175 million people already messaging companies through the platform.',
            'WhatCanWithWhatsApp' => 'WhatsApp supports the sending of marketing-related messages, including product recommendations, information alerts, relevant offers, appointment reminders, and cart abandonment reminders. With a click-through rate of 97%, start having engaging conversations with your customers on WhatsApp.',
            'useWhatsAppToList' => [
                'Send marketing communications to opted-in customers',
                'Use rich content including GIFs, images, and carousel messages',
                'Offer conversational customer support',
            ],
            'capabilitiesText' => 'Feature support differs across channels. Explore the channels availability and feature support below.',
            'capabilitiesTableOne' => [
                ['type' => 'Text', 'send' => 'full', 'receive' => 'full'],
                ['type' => 'Image', 'send' => 'full', 'receive' => 'full'],
                ['type' => 'File', 'send' => 'full', 'receive' => 'No support'],
                ['type' => 'Gif', 'send' => 'No support', 'receive' => 'No support'],
                ['type' => 'Location', 'send' => 'full', 'receive' => 'No support'],
                ['type' => 'Html', 'send' => 'full', 'receive' => 'full'],
                ['type' => 'Authentication', 'send' => 'full', 'receive' => 'No support'],
            ],
            'capabilitiesTableTwo' => [
                ['type' => 'Link', 'send' => 'full', 'receive' => 'full'],
                ['type' => 'Postback', 'send' => 'full', 'receive' => 'full'],
                ['type' => 'Reply', 'send' => 'full', 'receive' => 'full'],
                ['type' => 'Buy', 'send' => 'No support', 'receive' => 'No support'],
            ],
            'capabilitiesTableThree' => [
                ['type' => 'Carousel', 'send' => 'partial support', 'receive' => 'full'],
                ['type' => 'List', 'send' => 'full', 'receive' => 'full'],
            ],
        ];

        return $this->response(true, 'WhatsApp Setup Information', $whatsappSetupInfo);
    }

    /**
     * @OA\Post(
     *     path="/api/workspaces/{workspace_id}/channels/install",
     *     summary="Install a channel for a connector",
     *     description="Install a channel and link it to an existing connector.",
     *     operationId="installChannel",
     *     tags={"Channels"},
     *     @OA\Parameter(
     *          name="workspace_id",
     *          in="path",
     *          required=true,
     *          description="The workspace ID",
     *          @OA\Schema(type="string", format="uuid", example="d587ce39-6be2-48c0-b400-5d776464a112")
     *      ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="connector_id", type="string", format="uuid", description="The ID of the connector"),
     *             @OA\Property(property="name", type="string", description="Name of the channel"),
     *             @OA\Property(property="platform", type="string", enum={"whatsapp", "sms"}, description="Platform of the channel")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Channel installed successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation Error(s)"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function installChannel(Request $request, Workspace $workspace): JsonResponse
    {
        try {
            // Check if the user is authorized to create a channel
            //  $this->authorize('create', Channel::class);

            $validator = Validator::make(
                $request->all(),
                [
                    'connector_id' => 'required|uuid|exists:connectors,id|unique:channels,connector_id',
                    'name' => [
                        'required',
                        'string',
                        function ($attribute, $value, $fail) {
                            if ($value === $this->getTestChannelName()) {
                                $fail('The channel name cannot be "' . $this->getTestChannelName() . '" as it is reserved for system use.');
                            }
                        },
                    ],
                    'platform' => 'required|string',
                ]
            );

            if ($validator->fails()) {
                return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
            }

            $validatedData = $validator->validated();

            $status = $validatedData['platform'] == 'sms' ? 'pending' : 'active';

            $channel = Channel::create([
                'connector_id' => $validatedData['connector_id'],
                'name' => $validatedData['name'],
                'status' => $status,
                'platform' => $validatedData['platform'],
            ]);

            $channel->workspaces()->sync([$workspace->id]);

            return $this->response(true, 'Channel installed successfully', [
                'channel' => $channel,
            ]);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to create a channel.',
            ], 403);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/workspaces/{workspaceId}/channels",
     *     summary="Get channels with optional platform filter",
     *     description="Fetch the list of channels for a workspace, optionally filtering by platform.",
     *     operationId="getChannels",
     *     tags={"Channels"},
     *     @OA\Parameter(
     *         name="workspaceId",
     *         in="path",
     *         description="The ID of the workspace",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             example="04c51660-196a-468a-bf97-3fcef058944d"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="platform",
     *         in="query",
     *         description="Platform to filter channels by (e.g., whatsapp)",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             example="whatsapp"
     *         )
     *     ),
     *       @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="status to filter channels by (e.g., active)",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             example="whatsapp"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of channels with optional platform filter",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Channels retrieved"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="name", type="string", example="Whatsapp"),
     *                     @OA\Property(property="platform", type="string", example="whatsapp"),
     *                     @OA\Property(property="icon", type="string", example="url-to-whatsapp-icon"),
     *                     @OA\Property(property="status", type="string", example="Installed")
     *                 )
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
    public function getChannels(Request $request, Organization $organization, Workspace $workspace): JsonResponse
    {


        // Optional platform filter, specifically checking for WhatsApp
        $platform = $request->query('platform');
        $status = $request->query('status');
        $query = $workspace->channels();


        if ($platform === Channel::WHATSAPP_PLATFORM) {
            $query->with(['connector.whatsappConfiguration.whatsappBusinessAccount.whatsappPhoneNumbers']);
            $query->where('channels.platform', Channel::WHATSAPP_PLATFORM);
        }

        if ($platform === Channel::SMS_PLATFORM) {
            $query->with(['connector.smsConfiguration.sender']);
            $query->where('channels.platform', Channel::SMS_PLATFORM);
        }

        if ($status) {
            $query->where('status', $status);
        }
        // Fetch channels based on the query (filtered or all)
        $channels = $query->get();
        $channelsResponse = $channels->map(function ($channel) {
            return new \App\Http\Responses\Channel($channel);
        });

        return $this->response(true, 'Channels retrieved', $channelsResponse);
    }


    public function deleteChannel(Request $request, Workspace $workspace, Channel $channel): JsonResponse
    {

        //   Authorize delete deleteChannel
        if (!auth()->user()->can('delete', $channel)) {
            return response()->json(['error' => 'You do not have permission to delete this channel.'], 403);
        }
        try {
            // Ensure the channel belongs to the workspace
            if (!$workspace->channels()->where('channels.id', $channel->id)->exists()) {
                return $this->response(false, 'Channel does not belong to the specified workspace.', null, 403);
            }

            // Begin transaction to ensure atomic operations
            DB::beginTransaction();

            // Get the connector associated with the channel
            $connector = Connector::where('id', $channel->connector_id)->first();

            if ($connector) {
                // Clean up WhatsApp-related data if platform is WhatsApp
                if ($channel->platform === 'whatsapp') {
                    // Delete WhatsApp configurations
                    WhatsappConfiguration::where('connector_id', $connector->id)->delete();

                    // Unsubscribe webhooks (if applicable)
                    $this->unsubscribeFromWABA($connector);

                    // Deregister phone numbers
                    $this->deregisterPhoneNumber($connector);
                }

                if ($channel->platform === 'sms') {
                    //  sms configurations
                    $smsConfig = SmsConfiguration::where('connector_id', $connector->id)->first();
                    // delete sender
                    $this->deleteSender($smsConfig->sender_id);
                    // Delete sms configurations
                    $smsConfig->delete();
                }

                // Delete the connector
                $connector->delete();
            }

            // Delete the channel
            $channel->delete();

            // Commit the transaction
            DB::commit();

            return $this->response(true, 'Channel and related connector deleted successfully.', null);
        } catch (Throwable $e) {
            // Rollback transaction in case of error
            DB::rollBack();

            return $this->response(false, 'An error occurred while deleting the channel and connector: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/organizations/{organizationId}/channels",
     *     summary="Get channels by organization with optional platform filter",
     *     description="Fetch all channels associated with an organization's workspaces, optionally filtered by platform.",
     *     operationId="getChannelsByOrganization",
     *     tags={"Channels"},
     *     @OA\Parameter(
     *         name="organizationId",
     *         in="path",
     *         description="The ID of the organization",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="uuid"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="platform",
     *         in="query",
     *         description="Platform to filter channels by (e.g., whatsapp, sms)",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"whatsapp", "sms"}
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of channels for the organization",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Organization channels retrieved"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="string"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="platform", type="string"),
     *                     @OA\Property(property="status", type="string"),
     *                     @OA\Property(property="workspace", type="object")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Organization not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Organization not found")
     *         )
     *     )
     * )
     */
    public function getChannelsByOrganization(Request $request, Organization $organization): JsonResponse
    {
        try {
            // Get the platform filter from the request
            $platform = $request->query('platform');

            // Start with the organization's workspaces
            $query = Channel::whereHas('workspaces', function ($query) use ($organization) {
                $query->whereIn('workspaces.id', $organization->workspaces->pluck('id'));
            });

            // Apply platform filter if specified
            if ($platform) {
                $query->where('platform', $platform);
            }

            // Load relationships based on platform
            if ($platform === Channel::WHATSAPP_PLATFORM) {
                $query->with([
                    'connector.whatsappConfiguration.whatsappBusinessAccount.whatsappPhoneNumbers',
                    'workspaces'
                ]);
            } elseif ($platform === Channel::SMS_PLATFORM) {
                $query->with([
                    'connector.smsConfiguration.sender',
                    'workspaces'
                ]);
            } else {
                $query->with('workspaces');
            }

            // Get the channels
            $channels = $query->get();

            // Transform the channels using the Channel response class
            $channelsResponse = $channels->map(function ($channel) {
                return new \App\Http\Responses\Channel($channel);
            });

            return $this->response(true, 'Organization channels retrieved', $channelsResponse);
        } catch (Throwable $e) {
            return $this->response(false, 'Error retrieving organization channels: ' . $e->getMessage(), null, 500);
        }
    }
    private function deregisterPhoneNumber(Connector $connector): void
    {
        $whatsappConfig = WhatsappConfiguration::where('connector_id', $connector->id)->first();

        if ($whatsappConfig) {
            $phoneNumberId = $whatsappConfig->primary_whatsapp_phone_number_id;

            if ($phoneNumberId) {
                $endpoint = "https://graph.facebook.com/v21.0/{$phoneNumberId}/deregister";
                Http::post($endpoint, [
                    'access_token' => Meta::ACCESS_TOKEN,
                ]);
            }
        }
    }

    private function unsubscribeFromWABA(Connector $connector): void
    {
        $whatsappConfig = WhatsappConfiguration::where('connector_id', $connector->id)->first();

        if ($whatsappConfig) {
            $endpoint = "https://graph.facebook.com/v21.0/{$whatsappConfig->whatsapp_business_account_id}/subscribed_apps";
            Http::delete($endpoint, [
                'access_token' => Meta::ACCESS_TOKEN,
            ]);
        }
    }

    public function getSmsSetupInfo(): JsonResponse
    {
        $smsSetupInfo = [
            'preInstallation' => 'Before you can install SMS , you need to purchase and activate an SMS enabled phone number via the Senders Numbers connectors.:',
            'installation' => 'Once you’ve purchased your SMS enabled phone number, all you need to do when installing SMS by Dreams is to select the phone number from your list of active phone numbers and give the channel a unique name.',
            'installationList' => [],
            'overviewListImages' => [
                'https://is1-ssl.mzstatic.com/image/thumb/Purple113/v4/92/d6/d3/92d6d380-dbaf-1eeb-e7b7-436c3262d9e4/pr_source.png/430x0w.webp',
                'https://is1-ssl.mzstatic.com/image/thumb/Purple113/v4/92/d6/d3/92d6d380-dbaf-1eeb-e7b7-436c3262d9e4/pr_source.png/430x0w.webp',
                'https://is1-ssl.mzstatic.com/image/thumb/Purple113/v4/92/d6/d3/92d6d380-dbaf-1eeb-e7b7-436c3262d9e4/pr_source.png/430x0w.webp',
                'https://is1-ssl.mzstatic.com/image/thumb/Purple113/v4/92/d6/d3/92d6d380-dbaf-1eeb-e7b7-436c3262d9e4/pr_source.png/430x0w.webp',
                'https://is1-ssl.mzstatic.com/image/thumb/Purple113/v4/92/d6/d3/92d6d380-dbaf-1eeb-e7b7-436c3262d9e4/pr_source.png/430x0w.webp',
                'https://is1-ssl.mzstatic.com/image/thumb/Purple113/v4/92/d6/d3/92d6d380-dbaf-1eeb-e7b7-436c3262d9e4/pr_source.png/430x0w.webp',
            ],
            'overview' => 'SMS is a direct connections with over 240 carriers in 140 countries, you can send messages at scale without worrying about reliability or complexity.',
            'Use sms to' => 'Send one-time passwords to customers ,
                            Send cart abandonment notifications,
                            Let your customers set their delivery preferences ,
                            Target the right customers with personalized text messages',

            'capabilitiesText' => 'Feature support differs across channels. Explore the channels availability and feature support below.',
            'capabilitiesTableOne' => [
                ['type' => 'Text', 'send' => 'full', 'receive' => 'full'],
                ['type' => 'Image', 'send' => 'No support', 'receive' => 'No support'],
                ['type' => 'File', 'send' => 'No support', 'receive' => 'No support'],
                ['type' => 'Gif', 'send' => 'No support', 'receive' => 'No support'],
                ['type' => 'Location', 'send' => 'No support', 'receive' => 'No support'],
                ['type' => 'Html', 'send' => 'No support', 'receive' => 'No support'],
                ['type' => 'Authentication', 'send' => 'No support', 'receive' => 'No support'],
            ],
            'capabilitiesTableTwo' => [
                ['type' => 'Link', 'send' => 'full', 'receive' => 'full'],
                ['type' => 'Postback', 'send' => 'No support', 'receive' => 'No support'],
                ['type' => 'Reply', 'send' => 'full', 'receive' => 'full'],
                ['type' => 'Buy', 'send' => 'No support', 'receive' => 'No support'],
            ],
            'capabilitiesTableThree' => [
                ['type' => 'Carousel', 'send' => 'partial support', 'receive' => 'full'],
                ['type' => 'List', 'send' => 'full', 'receive' => 'full'],
            ],
        ];

        return $this->response(true, 'Sms Setup Information', $smsSetupInfo);
    }


    public function getSender($channel_id)
    {
        // Fetch the channel with the sender relationship
        $channel = Channel::with(['smsConfiguration.sender'])->find($channel_id);

        if (!$channel) {
            return $this->response(false, 'error', 'Channel not found', 404);
        }
        $sender = $channel->smsConfiguration?->sender;

        if (!$sender) {
            return $this->response(false, 'error', 'Sender not found', 404);
        }
        return $this->response(true, 'sender Information', $sender);
    }




    private function deleteSender($id)
    {

        $senderController = app(SendersController::class);
        $senderResponse = $senderController->destroy($id); // Pass the request data

        $responseData = $senderResponse->getData(true); // Parse JSON response
        if (isset($responseData['message'])) {
            return $responseData['message'];
        }
    }


    /**
     * @OA\Post(
     *     path="/api/workspaces/{workspace_id}/channels/{channel_id}/connect",
     *     summary="Connect a channel to a workspace",
     *     description="Associate an existing channel with a specific workspace",
     *     operationId="connectChannelToWorkspace",
     *     tags={"Channels"},
     *     @OA\Parameter(
     *         name="workspace_id",
     *         in="path",
     *         description="The ID of the workspace",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="uuid"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="channel_id",
     *         in="path",
     *         description="The ID of the channel to connect",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="uuid"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Channel connected successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Channel connected to workspace successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="channel", type="object"),
     *                 @OA\Property(property="workspace", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error or channel already connected",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Channel is already connected to this workspace")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized access",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized to connect channel to workspace")
     *         )
     *     )
     * )
     */
    public function connectChannelToWorkspace(Request $request, Workspace $workspace, Channel $channel): JsonResponse
    {
        try {
            // Verify the channel and workspace belong to the same organization
            if (!$this->validateOrganizationAccess($workspace, $channel)) {
                return $this->response(
                    false,
                    'Channel and workspace must belong to the same organization',
                    null,
                    400
                );
            }

            // Check if the channel is already connected to this workspace
            if ($channel->workspaces()->where('workspaces.id', $workspace->id)->exists()) {
                return $this->response(
                    false,
                    'Channel is already connected to this workspace',
                    null,
                    400
                );
            }

            // Begin transaction
            DB::beginTransaction();

            try {
                // Connect the channel to the workspace
                $channel->workspaces()->attach($workspace->id);

                // Load the updated relationships
                $channel->load(['workspaces', 'connector']);
                $workspace->load('channels');

                DB::commit();

                return $this->response(true, 'Channel connected to workspace successfully', [
                    'channel' => new \App\Http\Responses\Channel($channel),
                    'workspace' => $workspace
                ]);
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Throwable $e) {
            return $this->response(
                false,
                'Error connecting channel to workspace: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * Validate that the channel and workspace belong to the same organization
     */
    private function validateOrganizationAccess(Workspace $workspace, Channel $channel): bool
    {
        // Get organization's workspaces
        $organizationWorkspaces = $workspace->organization->workspaces()
            ->pluck('id')
            ->toArray();

        // Check if any of the channel's workspaces belong to the organization
        return $channel->workspaces()
            ->whereIn('workspaces.id', $organizationWorkspaces)
            ->exists();
    }

    public function getChannel(Workspace $workspace, Channel $channel)
    {
        if ($channel->platform == Channel::WHATSAPP_PLATFORM) {
            $whatsappConfiguration = $channel->whatsappConfiguration;

            if (!$whatsappConfiguration) {
                return $this->response(false, 'WhatsApp configuration not found for this channel.', null, 404);
            }

            $whatsappBusinessAccount = $whatsappConfiguration->whatsappBusinessAccount;
            $businessManagerAccount = $whatsappBusinessAccount?->businessManagerAccount;
            $whatsappPhoneNumber = $whatsappConfiguration->whatsappPhoneNumber;
            $whatsappBusinessProfile = $whatsappPhoneNumber?->whatsappBusinessProfile;


            return $this->response(
                true,
                'Channel information fetched successfully.',
                [
                    'channel' => new \App\Http\Responses\Channel($channel),
                    'business_manager_account' => $businessManagerAccount
                        ? new BusinessManagerAccount($businessManagerAccount)
                        : null,
                    'whatsapp_business_account' => $whatsappBusinessAccount
                        ? new WhatsAppBusinessAccount($whatsappBusinessAccount)
                        : null,
                    'whatsapp_phone_number' => $whatsappPhoneNumber
                        ? new WhatsappPhoneNumber($whatsappPhoneNumber)
                        : null,
                    'whatsapp_business_profile' => $whatsappBusinessProfile
                        ? new WhatsappBusinessProfile($whatsappBusinessProfile)
                        : null
                ]
            );
        }
    }

    public function destroy(Request $request, Channel $channel): JsonResponse
    {


        try {
            // Ensure the channel belongs to the workspace
            if (!$channel->platform === "sms") {
                return $this->response(false, 'Channel does not belong to the specified platform.', null, 403);
            }

            // Begin transaction to ensure atomic operations
            DB::beginTransaction();

            // Get the connector associated with the channel
            $connector = Connector::where('id', $channel->connector_id)->first();

            if ($connector) {

                // Delete SMS configurations
                $smsConfig = SmsConfiguration::where('connector_id', $connector->id)->first();
                if ($smsConfig) {
                    // Delete sender
                    $this->deleteSender($smsConfig->sender_id);
                    // Delete SMS configurations
                    $smsConfig->delete();
                }
                // Delete the connector
                $connector->delete();
            }

            // Delete the channel
            $channel->delete();

            // Commit the transaction
            DB::commit();

            return $this->response(true, 'Channel and related connector deleted successfully.', null);
        } catch (Throwable $e) {
            // Rollback transaction in case of error
            DB::rollBack();

            return $this->response(false, 'An error occurred while deleting the channel and connector: ' . $e->getMessage(), null, 500);
        }
    }
}
