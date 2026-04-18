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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Traits\ChannelManager;
use Exception;
use App\Traits\WalletManager;
use App\Models\Service as MService;
use App\Enums\Service as EnumService;

class ChannelControllerV2 extends BaseApiController
{
    use AuthorizesRequests, ChannelManager;
    use WalletManager;

    /* public function index(Request $request, Workspace $workspace): JsonResponse
    {

        // Optional platform filter, specifically checking for WhatsApp
        $platform = $request->query('platform');

        $status = $request->query('status');
        $query = $workspace->channels()
            ->when(env('LIVE_CHAT_FEATURE_FLAG') == false, function ($query) {
                $query->where('channels.platform', '!=', Channel::LIVECHAT_PLATFORM);
            })
            ->when(env('MESSENGER_FEATURE_FLAG') == false, function ($query) {
                $query->where('channels.platform', '!=', Channel::MESSENGER_PLATFORM);
            });


        if ($platform === Channel::WHATSAPP_PLATFORM) {
            $query->with(['connector.whatsappConfiguration.whatsappBusinessAccount.whatsappPhoneNumbers']);
            $query->where('channels.platform', Channel::WHATSAPP_PLATFORM);
        }

        if ($platform === Channel::SMS_PLATFORM) {
            $query->with(['connector.smsConfiguration.sender']);
            $query->where('channels.platform', Channel::SMS_PLATFORM);
        }

        if ($platform == Channel::LIVECHAT_PLATFORM) {
            $query->with(['connector.liveChatConfiguration.widget']);
            $query->where('channels.platform', Channel::LIVECHAT_PLATFORM);
        }

        if ($platform == Channel::TICKETING_PLATFORM) {
            $query->with(['connector.ticketConfiguration.ticketForm']);
            $query->where('channels.platform', Channel::TICKETING_PLATFORM);
        }

        if ($status) {
            $query->where('status', $status);
        }
        // Fetch channels based on the query (filtered or all)
        $channels = $query
            ->orderByDesc('channels.created_at')
            ->get();

        $channelsResponse = $channels->map(function ($channel) {
            return new \App\Http\Responses\Channel($channel);
        });

        return $this->response(true, 'Channels retrieved', $channelsResponse);
    }*/


    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        $platform = $request->query('platform');
        $status   = $request->query('status');

        $query = $workspace->channels();
            // ->when(env('LIVE_CHAT_FEATURE_FLAG') == false, function ($query) {
            //     $query->where('channels.platform', '!=', Channel::LIVECHAT_PLATFORM);
            // })
            // ->when(env('MESSENGER_FEATURE_FLAG') == false, function ($query) {
            //     $query->where('channels.platform', '!=', Channel::MESSENGER_PLATFORM);
            // });

        if ($platform === Channel::WHATSAPP_PLATFORM) {
            $query->with([
                'connector.whatsappConfiguration.whatsappBusinessAccount.whatsappPhoneNumbers'
            ])->where('channels.platform', Channel::WHATSAPP_PLATFORM);
        }

         if ($platform === Channel::MESSENGER_PLATFORM) {
            $query->with([
                'connector.messengerConfiguration.businessManager.metaPages'
            ])->where('channels.platform', Channel::MESSENGER_PLATFORM);
        }

        if ($platform === Channel::SMS_PLATFORM) {
            $query->with(['connector.smsConfiguration.sender'])
                ->where('channels.platform', Channel::SMS_PLATFORM);
        }

        if ($platform === Channel::LIVECHAT_PLATFORM) {
            $query->with(['connector.liveChatConfiguration.widget'])
                ->where('channels.platform', Channel::LIVECHAT_PLATFORM);
        }

        if ($platform === Channel::TICKETING_PLATFORM) {
            $query->with(['connector.ticketConfiguration.ticketForm'])
                ->where('channels.platform', Channel::TICKETING_PLATFORM);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $channels = $query
            ->orderByDesc('channels.created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $channels->getCollection()->transform(function ($channel) {
            return new \App\Http\Responses\Channel($channel);
        });

        return $this->paginateResponse(
            true,
            'Channels retrieved',
            $channels
        );
    }



    public function organizationIndex(Request $request, Organization $organization): JsonResponse
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

    public function processChannelPayment(Request $request, Organization $organization, Channel $channel): JsonResponse
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
                $paymentAmount = env('SNEDER_NAME_FEES') ?? 200;
                $paymentSuccessful = $this->processPayment($organization, -1 * $paymentAmount);
                if (!$paymentSuccessful) {
                    return $this->response(false, 'Payment failed. Please try again.', null, 402);
                }


                // Notify management about the payment
                \App\Services\ManagementNotificationService::notifyPayment(
                    $organization->name,
                    $channel->name,
                    $paymentAmount,
                    $channel->platform,
                    $channel->id
                );

                $smsConfig = SmsConfiguration::where('connector_id', $channel->connector_id)->first();
                if ($smsConfig) {
                    $smsConfig->update(['status' => SmsConfiguration::STATUS_PENDING]);
                }
                $channel->update(['status' => Channel::STATUS_PENDING]);
                \App\Models\Sender::where('id', $smsConfig->sender_id)->update([
                    'status' => \App\Models\Sender::STATUS_PAYMENT_CONFIRMATION
                ]);
                return $this->response(true, 'Channel activated successfully.', new \App\Http\Responses\Channel($channel));
            });
        } catch (Exception $e) {
            return $this->response(false, 'An error occurred while approve the channel.', 500);
        }
    }

    public function show(Workspace $workspace, Channel $channel)
    {
        $channel = $workspace->channels()->findOrFail($channel->id);

        return match ($channel->platform) {
            Channel::WHATSAPP_PLATFORM => $this->getWhatsAppChannelResponse($channel),
            Channel::SMS_PLATFORM => $this->getSmsChannelResponse($channel),
            Channel::LIVECHAT_PLATFORM => $this->getLiveChatChannelResponse($channel),
            Channel::TICKETING_PLATFORM => $this->getTicketChannelResponse($channel),
            default => $this->response(false, 'Unsupported channel platform.', null, 400)
        };
    }

    private function getWhatsAppChannelResponse(Channel $channel): JsonResponse
    {
        $whatsappConfiguration = $channel->whatsappConfiguration;

        if (!$whatsappConfiguration) {
            return $this->response(false, 'WhatsApp configuration not found for this channel.', null, 404);
        }

        $whatsappBusinessAccount = $whatsappConfiguration->whatsappBusinessAccount;
        $businessManagerAccount = $whatsappBusinessAccount?->businessManagerAccount;
        $whatsappPhoneNumber = $whatsappConfiguration->whatsappPhoneNumber;
        $whatsappBusinessProfile = $whatsappPhoneNumber?->whatsappBusinessProfile;

        return $this->response(true, 'Channel information fetched successfully.', [
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
        ]);
    }

    private function getSmsChannelResponse(Channel $channel): JsonResponse
    {
        $channel->load(['connector.smsConfiguration.sender']);

        return $this->response(
            true,
            'Channel information fetched successfully.',
            new \App\Http\Responses\Channel($channel)
        );
    }

    private function getLiveChatChannelResponse(Channel $channel): JsonResponse
    {
        // Load the channel with its livechat configuration, widget, and forms
        $channel->load([
            'connector.liveChatConfiguration.widget.preChatForms.fields',
            'connector.liveChatConfiguration.widget.postChatForms.fields'
        ]);

        return $this->response(
            true,
            'Channel information fetched successfully.',
            new \App\Http\Responses\Channel($channel)
        );
    }

    private function getTicketChannelResponse(Channel $channel): JsonResponse
    {
        // Load the channel with its ticket configuration, widget, and forms
        $channel->load([
            'connector.ticketConfiguration.ticketForm.ticketFormFields'
        ]);

        return $this->response(
            true,
            'Channel information fetched successfully.',
            new \App\Http\Responses\Channel($channel)
        );
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

    public function getWhatsappSetupInfo(): JsonResponse
    {
        $language = strtolower(request()->header('Accept-Language', 'en'));

        // Default: English
        $whatsappSetupInfoEn = [
            'preInstallation' => 'We highly recommend reading our WhatsApp onboarding guide to ensure a smooth installation before proceeding to the next steps. To install WhatsApp, make sure you have the following:',
            'preInstallationList' => [
                'Access to Facebook Business Manager',
                'A viable phone number for WhatsApp: this has to be your own phone number.',
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

        // Arabic (ae) Version — You can update this to actual Arabic translations
        $whatsappSetupInfoAe = [
            'preInstallation' => 'نوصي بشدة بقراءة دليل إعداد WhatsApp لضمان تثبيت سلس قبل المتابعة. لتثبيت WhatsApp، تأكد من توفر ما يلي:',
            'preInstallationList' => [
                'الوصول إلى مدير أعمال فيسبوك',
                'رقم هاتف صالح لـ WhatsApp: يمكن أن يكون رقمك الخاص.',
                'العنوان القانوني والتفاصيل الخاصة بنشاطك التجاري',
            ],
            'installation' => 'سوف نرشدك خلال عملية التثبيت، ونتأكد من استيفاء نشاطك التجاري ورقمك لمعايير WhatsApp والتحقق من حساب أعمال فيسبوك ورقم WhatsApp للأعمال.',
            'installationList' => [],
            'overviewListImages' => [
                'https://app.bird.com/channels/wa/wa-1.png',
                'https://app.bird.com/channels/wa/wa-2.png',
                'https://app.bird.com/channels/wa/wa-3.png',
                'https://app.bird.com/channels/wa/wa-4.png',
                'https://app.bird.com/channels/wa/wa-5.png',
                'https://app.bird.com/channels/wa/wa-6.png',
            ],
            'overview' => 'يعد WhatsApp من أكثر قنوات المراسلة شهرةً في العالم، حيث يستخدمه أكثر من 2 مليار شخص في 180 دولة، و175 مليون شخص يرسلون رسائل إلى الشركات عبره.',
            'WhatCanWithWhatsApp' => 'يدعم WhatsApp إرسال رسائل تسويقية مثل التوصيات، التنبيهات، العروض، تذكيرات المواعيد، وتذكيرات سلة الشراء المهجورة. بمعدل نقر يصل إلى 97%، ابدأ محادثات جذابة مع عملائك.',
            'useWhatsAppToList' => [
                'أرسل رسائل تسويقية للعملاء الذين وافقوا على الاستلام',
                'استخدم محتوى غني مثل الصور وGIF والرسائل المتعددة (Carousel)',
                'قدّم دعمًا للعملاء عبر المحادثات',
            ],
            'capabilitiesText' => 'تختلف الميزات المتاحة حسب القناة. استكشف توفر القنوات والدعم أدناه.',
            'capabilitiesTableOne' => [
                ['type' => 'نص', 'send' => 'كامل', 'receive' => 'كامل'],
                ['type' => 'صورة', 'send' => 'كامل', 'receive' => 'كامل'],
                ['type' => 'ملف', 'send' => 'كامل', 'receive' => 'لا يوجد دعم'],
                ['type' => 'GIF', 'send' => 'لا يوجد دعم', 'receive' => 'لا يوجد دعم'],
                ['type' => 'موقع', 'send' => 'كامل', 'receive' => 'لا يوجد دعم'],
                ['type' => 'HTML', 'send' => 'كامل', 'receive' => 'كامل'],
                ['type' => 'المصادقة', 'send' => 'كامل', 'receive' => 'لا يوجد دعم'],
            ],
            'capabilitiesTableTwo' => [
                ['type' => 'رابط', 'send' => 'كامل', 'receive' => 'كامل'],
                ['type' => 'Postback', 'send' => 'كامل', 'receive' => 'كامل'],
                ['type' => 'Reply', 'send' => 'كامل', 'receive' => 'كامل'],
                ['type' => 'شراء', 'send' => 'لا يوجد دعم', 'receive' => 'لا يوجد دعم'],
            ],
            'capabilitiesTableThree' => [
                ['type' => 'Carousel', 'send' => 'دعم جزئي', 'receive' => 'كامل'],
                ['type' => 'قائمة', 'send' => 'كامل', 'receive' => 'كامل'],
            ],
        ];

        // Return based on Accept-Language
        $whatsappSetupInfo = $language === 'ae' ? $whatsappSetupInfoAe : $whatsappSetupInfoEn;

        return $this->response(true, 'WhatsApp Setup Information', $whatsappSetupInfo);
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

    public function getLiveChatSetupInfo()
    {
        // TODO: return info
        return $this->response(true, 'Live Chat Setup Information', []);
    }

    public function getTicketingSetupInfo()
    {
        // TODO: return info
        return $this->response(true, 'Live Chat Setup Information', []);
    }


    public function getAvailableChannels($workspace_id)
    {
        // Verify that the workspace exists
        $workspace = Workspace::find($workspace_id);
        if (!$workspace) {
            return $this->response(false, 'Workspace not found', null, 404);
        }

        // List of all possible channels
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
            [
                'name' => 'Live Chat',
                'platform' => 'livechat',
                'icon' => 'url-to-sms-icon',
            ],
            [
                'name' => 'Messenger',
                'platform' => 'messenger',
                'icon' => 'url-to-messenger-icon',
            ],
            [
                'name' => 'Ticketing',
                'platform' => 'ticketing',
                'icon' => 'url-to-ticketing-icon',
            ]
        ];

        // Filter based on feature flags
        // $availableChannels = array_filter($availableChannels, function ($channel) {
        //     if ($channel['platform'] === 'livechat' && !env('LIVE_CHAT_FEATURE_FLAG')) {
        //         return false;
        //     }
        //     if ($channel['platform'] === 'messenger' && !env('MESSENGER_FEATURE_FLAG')) {
        //         return false;
        //     }
        //     return true;
        // });

        // Fetch installed channels
        $installedChannels = $workspace->channels()->pluck('platform')->toArray();

        // Mark as installed or not, and cast to object
        $availableChannels = array_map(function ($channel) use ($installedChannels) {
            $channel['status'] = in_array($channel['platform'], $installedChannels) ? 'Installed' : 'Install';
            return (object) $channel;
        }, $availableChannels);

        return $this->response(true, 'Available Channels', array_values($availableChannels));
    }



    public function installChannel(Request $request, Workspace $workspace): JsonResponse
    {
        try {

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
            DB::beginTransaction();
            try {
                // Get the organization ID from the workspace
                if ($validatedData['platform'] === 'sms') {
                    $this->removeDefaultSmsChannels($workspace->organization_id);
                }
                $status = $validatedData['platform'] == 'sms' ? 'pending' : 'active';

                $channel = Channel::create([
                    'connector_id' => $validatedData['connector_id'],
                    'name' => $validatedData['name'],
                    'status' => $status,
                    'platform' => $validatedData['platform'],
                    'default_workspace_id' => $workspace->id
                ]);
                $channel->workspaces()->sync([$workspace->id]);
                DB::commit();

                // Notify management about the new channel
                if ($validatedData['platform'] === 'sms') {
                    \App\Services\ManagementNotificationService::notifySenderNameRequest(
                        $workspace->organization->name,
                        $channel->name,
                        $channel->id
                    );
                }
                return $this->response(true, 'Channel installed successfully', [
                    'channel' => $channel,
                ]);
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to create a channel.',
            ], 403);
        } catch (Exception $e) {
            return $this->response(false, 'Error installing channel: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Remove default SMS channels for an organization
     *
     * @param string $organizationId
     * @return void
     */
    private function removeDefaultSmsChannels(string $organizationId): void
    {
        // Find any default SMS channels for this organization
        $defaultChannels = Channel::whereHas('workspaces', function ($query) use ($organizationId) {
            $query->whereHas('organization', function ($q) use ($organizationId) {
                $q->where('id', $organizationId);
            });
        })->whereHas('connector.smsConfiguration', function ($query) {
            $query->where('sender_id', 0);
        })->get();

        // Delete default channels and their connectors if found
        foreach ($defaultChannels as $defaultChannel) {
            // Get the connector
            $connector = $defaultChannel->connector;

            // Delete SMS configuration
            if ($connector) {
                SmsConfiguration::where('connector_id', $connector->id)->delete();
            }

            // Detach workspaces and delete channel
            $defaultChannel->workspaces()->detach();
            $defaultChannel->delete();

            // Delete connector
            if ($connector) {
                $connector->delete();
            }
        }
    }
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

    public function destroy(Workspace $workspace, Channel $channel): JsonResponse
    {

        try {
            // Ensure the channel belongs to the workspace
            if (!$workspace->channels()->where('channels.id', $channel->id)->exists()) {
                return $this->response(false, 'Channel does not belong to the specified workspace.', null, 403);
            }

            // Begin transaction to ensure atomic operations
            DB::beginTransaction();
            // Store channel info for notification before deletion
            $channelName = $channel->name;
            $channelPlatform = $channel->platform;
            $organizationName = $workspace->organization->name;
            // Count how many workspaces are using this channel
            $workspaceCount = $channel->workspaces()->count();

            if ($workspaceCount > 1) {
                // If channel is used by multiple workspaces, just detach from current workspace
                $channel->workspaces()->detach($workspace->id);
                DB::commit();
                return $this->response(true, 'Channel disconnected from workspace successfully.', null);
            }

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
            $channel->workspaces()->detach();
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

    public function update(Request $request, Workspace $workspace, Channel $channel)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'string',
                'sender' => 'array',
                'default_workspace_id' => 'uuid|exists:workspaces,id', // ✅ added
            ]
        );

        if ($validator->fails()) {
            return $this->response(
                false,
                'Validation Error(s)',
                new ValidatorErrorResponse($validator->errors()->toArray()),
                400
            );
        }

        DB::beginTransaction();
        try {
            $channel = $workspace->channels()->findOrFail($channel->id);
            $validatedData = $validator->validated();

            // ✅ SMS-specific logic stays as is
            if ($channel->platform === Channel::SMS_PLATFORM) {
                $this->updateSmsConfiguration($channel, $validatedData);
            }

            unset($validatedData['status']);

            // ✅ Handle default workspace update
            if (isset($validatedData['default_workspace_id'])) {
                $newDefault = Workspace::find($validatedData['default_workspace_id']);

                if (!$newDefault || $newDefault->organization_id !== $workspace->organization_id) {
                    return $this->response(
                        false,
                        'Invalid default workspace. It must belong to the same organization.',
                        null,
                        400
                    );
                }

                $channel->default_workspace_id = $validatedData['default_workspace_id'];
                unset($validatedData['default_workspace_id']);
            }

            // ✅ Update everything else normally
            $channel->update($validatedData);

            DB::commit();
            return $this->response(
                true,
                'Channel updated successfully',
                new \App\Http\Responses\Channel($channel)
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->response(false, 'Failed to update channel: ' . $e->getMessage(), null, 500);
        }
    }


    private function updateSmsConfiguration(Channel $channel, array $validatedData)
    {
        if (isset($validatedData['sender'])) {
            // Load the SMS configuration with sender relationship
            $channel->load('connector.smsConfiguration.sender');

            // Check if SMS configuration and sender exist
            if (!$channel->smsConfiguration?->sender) {
                throw new Exception('SMS sender configuration not found for this channel.');
            }
            if (isset($validatedData['sender']['name']) && $validatedData['sender']['name'] != $channel->smsConfiguration->sender->name) {
                $validatedData['sender']['status'] = \App\Models\Sender::STATUS_PENDING;
                $channel->update(['status' => Channel::STATUS_PENDING]);
                $channel->smsConfiguration->update(['status' => SmsConfiguration::STATUS_PENDING]);
            }
            unset($validatedData['sender']['status']);
            //TODO: return status senser and cahnnel pending if update sender name
            $channel->smsConfiguration->sender->update($validatedData['sender']);
        }
    }
}
