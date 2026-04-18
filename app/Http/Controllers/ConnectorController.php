<?php

namespace App\Http\Controllers;

use App\Constants\Meta;
use App\Models\Channel;
use App\Models\Connector;
use App\Models\LiveChatConfiguration;
use App\Models\MessengerConfiguration;
use App\Models\SmsConfiguration;
use App\Models\WhatsappConfiguration;
use App\Models\TicketConfiguration;
use App\Models\Widget;
use App\Models\Workspace;
use App\Models\TicketForm;
use App\Models\TicketFormLicense;
use App\Traits\ConnectorManager;
use App\Traits\MessengerOnboardingManager;
use App\Traits\WhatsappOnboardingManager;
use App\Traits\TicketManager;
use App\Traits\WalletManager;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;
use Carbon\Carbon;
use Closure;
use DB;
use Exception;


class ConnectorController extends BaseApiController
{

    use WhatsappOnboardingManager, AuthorizesRequests, MessengerOnboardingManager, ConnectorManager, TicketManager, WalletManager;

    /**
     * @OA\Post(
     *     path="/api/workspaces/{workspace_id}/connectors",
     *     summary="Create a new connector",
     *     description="Creates a connector for a specified platform and optionally performs WhatsApp onboarding.",
     *     operationId="createConnector",
     *     tags={"Connectors"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="workspace_id", type="string", format="uuid", description="The workspace ID"),
     *             @OA\Property(property="name", type="string", description="Name of the connector"),
     *             @OA\Property(property="status", type="string", description="Status of the connector"),
     *             @OA\Property(property="region", type="string", description="Region for the connector"),
     *             @OA\Property(property="platform", type="string", enum={"whatsapp", "sms","livechat"}, description="Platform type (e.g., 'whatsapp' or 'sms')"),
     *             @OA\Property(property="whatsapp_business_account_id", type="string", description="WhatsApp Business Account ID", nullable=true),
     *             @OA\Property(property="business_manager_account_id", type="string", description="Business Manager Account ID", nullable=true),
     *             @OA\Property(property="code", type="string", description="Authorization code for onboarding", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Connector created successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error or onboarding error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation Error(s)"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function createConnector(Request $request): JsonResponse
    {
        try {
            $validatedData = $this->validateConnectorRequest($request);
            return DB::transaction(function () use ($validatedData, $request) {
                $connector = $this->createBaseConnector($validatedData);

                $validatedData['connector_id'] = $connector->id;

                $handler = $this->resolvePlatformHandler($validatedData['platform']);
                $result = $handler($validatedData, $request->all());

                if (!$result['success']) {
                    throw new Exception($result['message'] ?? 'Failed to configure platform.');
                }

                return $this->response(true, 'Connector created successfully', [
                    'connector' => $connector,
                ]);
            });
        } catch (Throwable $e) {
            return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }

    private function resolvePlatformHandler(string $platform): Closure
    {
        return match ($platform) {
            'whatsapp' => fn($data, $requestAll) => $this->handleWhatsAppConnector($data),
            'messenger' => fn($data, $requestAll) => $this->handleMessengerConnector($data),
            'sms' => fn($data, $requestAll) => $this->handleSmsConnector($data, $requestAll),
            'livechat' => fn($data, $requestAll) => $this->handleLiveChat($data),
            'ticketing' => fn($data, $requestAll) => $this->handleTicketing($data),
            default => fn() => ['success' => false, 'message' => 'Unsupported platform.'],
        };
    }

    private function handleWhatsAppConnector(array $data): array
    {
        $onboarding = $this->handleWhatsAppOnboarding($data);

        if (!$onboarding['success'])
            return $onboarding;

        $existing = WhatsappConfiguration::where('business_manager_account_id', $onboarding['business_manager_id'])
            ->where('whatsapp_business_account_id', $data['whatsapp_business_account_id'])
            ->where('primary_whatsapp_phone_number_id', $data['whatsapp_phone_number_id'])
            ->first();


        if ($existing) {
            // Delete the connector that was just created
            Connector::where('id', $data['connector_id'])->delete();

            return [
                'success' => false,
                'message' => 'A WhatsApp configuration with the same business manager, WABA ID, and phone number already exists.',
            ];
        }


        $config = WhatsappConfiguration::create([
            'connector_id' => $data['connector_id'],
            'business_manager_account_id' => $onboarding['business_manager_id'],
            'whatsapp_business_account_id' => $data['whatsapp_business_account_id'],
            'primary_whatsapp_phone_number_id' => $data['whatsapp_phone_number_id'],
            'status' => 'active',
            'is_sandbox' => $data['is_sandbox'] ?? false,
        ]);


        return $config ? ['success' => true] : ['success' => false, 'message' => 'Failed to create WhatsApp configuration.'];
    }

    private function handleWhatsAppOnboarding(array $validatedData): array
    {
        try {
            $clientId = env('WHATSAPP_APP_ID');
            $clientSecret = env('WHATSAPP_APP_SECRET');
            $accessToken = Meta::ACCESS_TOKEN;

            if (!$clientId || !$clientSecret || !$accessToken) {
                throw new Exception('Missing WhatsApp API credentials in environment variables.');
            }

            $onboardingResult = $this->performOnboarding(
                $clientId,
                $clientSecret,
                $validatedData['code'],
                $accessToken,
                $validatedData['whatsapp_business_account_id'],
                $validatedData['whatsapp_phone_number_id'],
            );

            if (!$onboardingResult['success']) {
                throw new Exception($onboardingResult['message']);
            }

            return [
                'success' => true,
                'business_manager_id' => $onboardingResult['business_manager_id'],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Onboarding failed: ' . $e->getMessage(),
            ];
        }
    }

    private function handleMessengerConnector(array $data): array
    {
        $onboarding = $this->handleMessengerOnboarding($data);

        if (!$onboarding['success']) {
            return $onboarding;
        }

        $businessManagerId = $onboarding['data']['business_manager_id'] ?? null;
        $pages = $onboarding['data']['pages'] ?? [];

        if (!$businessManagerId || empty($pages)) {
            return ['success' => false, 'message' => 'Messenger onboarding did not return valid business manager or pages.'];
        }

        $firstPage = $pages[0] ?? null;

        if (!$firstPage || empty($firstPage['page_id'])) {
            return ['success' => false, 'message' => 'No valid page found in onboarding response.'];
        }

        $config = MessengerConfiguration::create([
            'connector_id' => $data['connector_id'],
            'business_manager_account_id' => $businessManagerId,
            'meta_page_id' => $firstPage['page_id'],
            'status' => 'active',
        ]);

        if (!$config) {
            return [
                'success' => false,
                'message' => "Failed to create Messenger configuration for page ID: {$firstPage['page_id']}",
            ];
        }

        return ['success' => true];
    }

    private function handleMessengerOnboarding(array $validatedData): array
    {
        try {
            $clientId = env('WHATSAPP_APP_ID');
            $clientSecret = env('WHATSAPP_APP_SECRET');
            $accessToken = Meta::ACCESS_TOKEN;

            if (!$clientId || !$clientSecret || !$accessToken) {
                throw new Exception('Missing WhatsApp API credentials in environment variables.');
            }

            $onboardingResult = $this->performMessengerOnboarding(
                $clientId,
                $clientSecret,
                $validatedData['code'],
                $accessToken
            );

            if (!$onboardingResult['success']) {
                throw new Exception($onboardingResult['message']);
            }

            return $onboardingResult;
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Onboarding failed: ' . $e->getMessage(),
            ];
        }
    }

    private function handleSmsConnector(array $data, array $requestAll): array
    {
        try {
            // Retrieve organization ID from workspace
            $workspace = Workspace::findOrFail($data['workspace_id']);
            $requestAll['organization_id'] = $workspace->organization_id;

            // Process sender and validate
            $senderResponse = $this->handleSmsSender($requestAll);

            if (!$senderResponse['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to create SMS sender.',
                    'validation_errors' => $senderResponse['validation_errors'] ?? [],
                ];
            }

            // Store the SMS configuration
            $smsConfig = SmsConfiguration::create([
                'connector_id' => $data['connector_id'],
                'sender_id' => $senderResponse['sender']['id'],
                'status' => $senderResponse['sender']['status'] ?? 'pending',
            ]);

            if (!$smsConfig) {
                return ['success' => false, 'message' => 'Failed to create SMS configuration.'];
            }

            return ['success' => true];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'SMS connector error: ' . $e->getMessage(),
            ];
        }
    }

    private function handleSmsSender(array $data)
    {
        try {
            $senderController = app(SenderController::class);
            $senderResponse = $senderController->store(new Request($data)); // Pass the request data

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

    private function handleLiveChat(array $validatedData): array
    {
        try {
            // Get the workspace from the validated data
            $workspace = Workspace::findOrFail($validatedData['workspace_id']);
            // Create a new widget for the organization
            $widget = Widget::create([
                'organization_id' => $workspace->organization_id,
                'theme_color' => $validatedData['theme_color'] ?? '#4CAF50',
                'logo_url' => $validatedData['logo_url'] ?? null,
                'welcome_message' => $validatedData['welcome_message'] ?? __('message.How can we help you today?'),
                'offline_message' => $validatedData['offline_message'] ?? __('message.We are currently offline. Please leave a message and we will get back to you.'),
                'is_active' => $validatedData['is_active'] ?? true,
                'show_agent_avatar' => $validatedData['show_agent_avatar'] ?? false,
                'show_agent_name' => $validatedData['show_agent_name'] ?? true,
                'show_file_upload' => $validatedData['show_file_upload'] ?? true,
                'position' => $validatedData['position'] ?? 'right',
                'z_index' => $validatedData['z_index'] ?? 999,
                'language' => $validatedData['language'] ?? app()->getLocale(),
                'working_hours_enabled' => $validatedData['working_hours_enabled'] ?? false,
                'working_hours' => $validatedData['working_hours'] ?? null,
                'require_name_email' => $validatedData['require_name_email'] ?? true,
                'sound_enabled' => $validatedData['sound_enabled'] ?? true,
                'auto_open' => $validatedData['auto_open'] ?? false,
                'auto_open_delay' => $validatedData['auto_open_delay'] ?? 10,
                'collect_visitor_data' => $validatedData['collect_visitor_data'] ?? true,
                'message_placeholder' => $validatedData['message_placeholder'] ?? __('message.livechat_message_placeholder'),
                'allowed_domains' => isset($validatedData['allowed_domains']) ? json_encode($validatedData['allowed_domains']) : null
            ]);

            if (!$widget) {
                throw new Exception('Failed to create LiveChat Widget.');
            }

            // Create LiveChat configuration linked to the connector and widget
            $liveChatConfig = LiveChatConfiguration::create([
                'connector_id' => $validatedData['connector_id'],
                'widget_id' => $widget->id,
                'status' => 'active'
            ]);

            if (!$liveChatConfig) {
                throw new Exception('Failed to create LiveChat Configuration.');
            }
            //dd($validatedData);
            // Create a channel for the connector
            $channel = Channel::create([
                'connector_id' => $validatedData['connector_id'],
                'name' => $validatedData['name'] ?? 'LiveChat Channel',
                'status' => 'active',
                'platform' => 'livechat'
            ]);

            if (!$channel) {
                throw new Exception('Failed to create LiveChat Channel.');
            }

            // Associate channel with the workspace
            $channel->workspaces()->attach($validatedData['workspace_id']);

            // Create default pre-chat form for the channel
            $preChatForm = $this->createDefaultPreChatForm($channel->id, $widget->id, $validatedData);

            // Create default post-chat form for the channel
            $postChatForm = $this->createDefaultPostChatForm($channel->id, $widget->id, $validatedData);


            return [
                'success' => true,
                'widget' => $widget,
                'configuration' => $liveChatConfig,
                'channel' => $channel,
                'pre_chat_form' => $preChatForm->load('fields'),
                'post_chat_form' => $postChatForm->load('fields')
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'LiveChat setup failed: ' . $e->getMessage(),
            ];
        }
    }

    private function handleTicketing(array $validatedData): array
    {
        try {
            // Get the workspace from the validated data
            $workspace = Workspace::findOrFail($validatedData['workspace_id']);

            //TODO: check if the Organizationd has a license


            // $licenseExpiresAt = Carbon::now()->addMonths($validatedData['license_duration_months'] ?? 1);

            // $license = TicketFormLicense::create([
            //     'workspace_id' => $workspace->id,
            //     'valid_from' => Carbon::now(),
            //     'expires_at' => $licenseExpiresAt,
            //     'max_forms' => $validatedData['max_forms'] ?? 2,
            //     'max_submissions_per_month' => $validatedData['max_submissions_per_month'] ?? null,
            //     'is_active' => true,
            // ]);

            // if ($license->hasReachedFormLimit()) {
            //     throw new Exception('License has reached the maximum number of forms.');
            // }
            // if ($license->getCurrentMonthSubmissions() >= ($license->max_submissions_per_month ?? 10)) {
            //     throw new Exception('License has reached the maximum number of submissions for this month.');
            // }
            // if ($license->expires_at->isPast()) {
            //     throw new Exception('License has expired.');
            // }
            // if ($license->isValid()) {
            //     $license->is_active = true;
            // } else {
            //     $license->is_active = false;
            // }
            // $license->save();
            $FormData = [
                'name' => $validatedData['name'] ?? 'Default Ticket Form',
                'status' => 'active',
                // 'license_id' => $license->id,
                'created_by' => auth()->user()->id,
                'organization_id' => $workspace->organization_id,
            ];
            if (isset($validatedData['description'])) {
                $FormData['description'] = $validatedData['description'];
            }
            if (isset($validatedData['theme_color'])) {
                $FormData['theme_color'] = $validatedData['theme_color'];
            }
            if (isset($validatedData['success_message'])) {
                $FormData['success_message'] = $validatedData['success_message'];
            }
            if (isset($validatedData['submit_button_text'])) {
                $FormData['submit_button_text'] = $validatedData['submit_button_text'];
            }
            $ticketForm = new TicketForm($FormData);
            if (!$ticketForm->save()) {
                throw new Exception('Failed to create Ticket Form.');
            }


            // Create TicketConfiguration
            $ticketConfig = TicketConfiguration::create([
                'connector_id' => $validatedData['connector_id'],
                'ticket_form_id' => $ticketForm->id,
                'status' => 'active'
            ]);

            if (!$ticketConfig) {
                throw new Exception('Failed to create Ticket Configuration.');
            }

            // Create a channel for the connector if requested
            $channel = Channel::create([
                'connector_id' => $validatedData['connector_id'],
                'name' => $validatedData['channel_name'] ?? $validatedData['name'] ?? 'Ticket Support Channel',
                'status' => 'active',
                'platform' => 'ticketing'
            ]);

            $token = hash('sha256', $channel->id . config('app.key') . time());
            $ticketForm->update(['iframe_token' => $token]);

            if (!$channel) {
                throw new Exception('Failed to create Ticketing Channel.');
            }

            // Associate channel with the workspace
            $channel->workspaces()->attach($validatedData['workspace_id']);
            // Create default contact form if requested
            $this->createDefaultContactForm($ticketForm->id);

            return [
                'success' => true,
                // 'license' => $license,
                'configuration' => $ticketConfig,
                'channel' => $channel,
                'ticket_form' => $ticketForm
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Ticketing setup failed: ' . $e->getMessage(),
            ];
        }
    }
}
