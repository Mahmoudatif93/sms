<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseApiController;
use App\Models\Channel;
use App\Models\SmsConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Responses\ValidatorErrorResponse;
use App\Models\Gateway;
use App\Models\GatewaySender;
use App\Services\FileUploadService;
use DB, Validator, Exception;
class ChannelController extends BaseApiController
{
    protected $fileUploadService;
    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }
    public function index(Request $request): JsonResponse
    {

        // Get pagination parameters
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);
        // Optional platform filter, specifically checking for WhatsApp
        $platform = $request->query('platform');
        $status = $request->query('status');
        $name = $request->query('name');
        $organization_name = $request->query('organization_name');
        $gateway_id = $request->query('gateway_id');
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
        if ($name) {
            $query->where('name', 'like', '%' . $name . '%');
        }
        if ($gateway_id) {
            $query->where('gateway_id', $gateway_id);
        }
        if ($organization_name) {
            $query->whereHas('workspaces.organization', function ($query) use ($organization_name) {
                $query->where('name', 'like', '%' . $organization_name . '%');
            });
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
            $channel->load([
                'connector.smsConfiguration.sender.gateways' => function ($query) {
                    $query->take(1); // This will limit to the first gateway
                }
            ]);
        }
        return $this->response(true, 'Channel retrieved', new \App\Http\Responses\Channel($channel));
    }

    public function update(Channel $channel, Request $request): JsonResponse
    {
        // Get the raw request content
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
        DB::beginTransaction();
        try {
            if ($channel->platform === Channel::SMS_PLATFORM) {
                $this->updateSmsConfiguration($channel, $validatedData);
            }

            $channel->update($validatedData);

            DB::commit();
            return $this->response(true, 'Channel updated successfully', new \App\Http\Responses\Channel($channel));

        } catch (Exception $e) {
            DB::rollBack();
            return $this->response(false, 'Failed to update channel: ' . $e->getMessage(), null, 500);
        }
    }

    private function updateSmsConfiguration(Channel $channel, array $validatedData)
    {
        if (isset($validatedData['sender'])) {
            $sender = $validatedData['sender'];
            // Load the SMS configuration with sender relationship
            $channel->load('connector.smsConfiguration.sender');

            // Check if SMS configuration and sender exist
            if (!$channel->smsConfiguration?->sender) {
                throw new Exception('SMS sender configuration not found for this channel.');
            }

            // If gateway_id is provided in the sender data
            if (isset($sender['gateway_id'])) {
                $senderId = $channel->smsConfiguration->sender->id;
                $gatewayId = $sender['gateway_id'];

                // Check if the relationship already exists
                $existingRelation = GatewaySender::where('sender_id', $senderId)
                    ->where('gateway_id', $gatewayId)
                    ->first();

                if (!$existingRelation) {
                    // Create new relationship if it doesn't exist
                    GatewaySender::create([
                        'gateway_id' => $gatewayId,
                        'sender_id' => $senderId
                    ]);
                } else {
                    // Update existing relationship
                    $existingRelation->update([
                        'gateway_id' => $gatewayId
                    ]);
                }
            }
            // Remove gateway_id from validatedData['sender'] as it's handled separately
            unset($sender['organizationName'], $sender['channelName'], $sender['file_authorization_letter'], $sender['file_other']);
            if (isset($sender['contract_expiration_date'])) {
                $sender['contract_expiration_date'] = date('Y-m-d', strtotime($sender['contract_expiration_date']));
            }
            if (isset($sender['expire_date'])) {
                $sender['expire_date'] = date('Y-m-d', strtotime($sender['expire_date']));
            }
            if (isset($sender['file_authorization_letter'])) {
                $sender['file_authorization_letter'] = $this->fileUploadService->upload($sender['authorization_letter']);
            }
            if (isset($sender['file_other'])) {
                $sender['file_other'] = $this->fileUploadService->upload($sender['other']);
            }

            //TODO: return status sender and channel pending if update sender name
            $channel->smsConfiguration->sender->update($sender);
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
                $workspace = $channel->workspaces()->first();
                if ($workspace) {
                    $organization = $workspace->organization;
                    $owner = $organization->owner;
                    
                    // If we have an owner, we could notify them here
                    if ($owner) {
                        // Example: Send notification to owner
                        $owner->notify(new \App\Notifications\ChannelStatusChanged($channel, $organization, 'approved'));                     }
                }
                $this->handleApproveSmsChannel($smsConfig->sender_id);
            });

            return $this->response(true, 'Channel approved successfully', null);
        } catch (Exception $e) {
            return $this->response(false, 'An error occurred while approve the channel.', 500);
        }
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
                $workspace = $channel->workspaces()->first();
                if ($workspace) {
                    $organization = $workspace->organization;
                    $owner = $organization->owner;
                    
                    // If we have an owner, we could notify them here
                    if ($owner) {
                        // Example: Send notification to owner
                        $owner->notify(new \App\Notifications\ChannelStatusChanged($channel, $organization, 'rejected'));                     }
                }
                $this->handleRejectSmsChannel($smsConfig->sender_id);
            });

            return $this->response(true, 'Channel approved successfully', null);
        } catch (Exception $e) {
            return $this->response(false, 'An error occurred while reject the channel.', 500);

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
                 // Get the workspace and organization owner to notify them
                 $workspace = $channel->workspaces()->first();
                 if ($workspace) {
                     $organization = $workspace->organization;
                     $owner = $organization->owner;
                     
                     // If we have an owner, we could notify them here
                     if ($owner) {
                         // Example: Send notification to owner
                         $owner->notify(new \App\Notifications\ChannelStatusChanged($channel, $organization, 'payment_required'));                     }
                 }
                $this->handleWaitingPaymentSmsChannel($smsConfig->sender_id);
            });

            return $this->response(true, 'Channel approved successfully', null);
        } catch (Exception $e) {
            return $this->response(false, 'An error occurred while reject the channel.', 500);

        }
    }

    protected function handleWaitingPaymentSmsChannel($sender_id)
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

    public function getGatewayNames()
    {
        $gateways = Gateway::select('id', 'name')->get();
        return $this->response(true, 'Gateways Information', $gateways);
    }

}
