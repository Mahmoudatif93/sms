<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Channel;
use App\Models\ContactEntity;
use App\Models\WhatsappConsumerPhoneNumber;
use App\Models\WhatsappMessage;
use App\Models\Workspace;
use Illuminate\Http\Request;

class WhatsappMessageReportController extends BaseApiController
{
    /**
     * Convert milliseconds timestamp to Carbon date.
     */
    private function convertTimestamp($timestamp)
    {
        if (!$timestamp) {
            return null;
        }
        return date('Y-m-d H:i:s', $timestamp / 1000);
    }

    /**
     * Fetch paginated WhatsApp messages for a workspace.
     *
     * @param Request $request
     * @param int $workspace The workspace ID.
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMessages(Request $request, Workspace $workspace)
    {
        // Get pagination parameters
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        // Extract filters from request
        $channelId = $request->get('channel_id');
        $type = $request->get('type');
        $status = $request->get('status');
        $direction = $request->get('direction');
        $createdFrom = $request->get('created_from'); // Start of the range
        $createdTo = $request->get('created_to');     // End of the range

        // Build the query dynamically
        $messagesQuery = WhatsappMessage::with('channel')->whereHas('whatsappPhoneNumber.whatsappConfiguration.connector.channel', function ($query) use ($workspace, $channelId) {
            $query->where('platform', Channel::WHATSAPP_PLATFORM)
                ->whereHas('workspaces', function ($workspaceQuery) use ($workspace) {
                    $workspaceQuery->where('workspaces.id', $workspace->id);
                });

            // Filter by channel ID if provided
            if ($channelId) {
                $query->where('id', $channelId);
            }
            $query->orderBy('created_at');
        });


        // Apply additional filters
        if ($type) {
            $messagesQuery->where('type', $type);
        }

        if ($status) {
            $messagesQuery->where('status', $status);
        }

        if ($direction) {
            $messagesQuery->where('direction', $direction);
        }

        if ($createdFrom && $createdTo) {
            $messagesQuery->whereBetween('created_at', [$createdFrom, $createdTo]);
        } elseif ($createdFrom) {
            $messagesQuery->where('created_at', '>=', $createdFrom);
        } elseif ($createdTo) {
            $messagesQuery->where('created_at', '<=', $createdTo);
        }

        // Paginate the filtered results
        $messages = $messagesQuery->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        // Transform the messages
        $data = $messages->getCollection()->map(function ($message) {
            return new \App\Http\Responses\WhatsappMessage($message);
        });

        // Set the transformed data back to the paginator
        $messages->setCollection($data);

        // Return a paginated response
        return $this->paginateResponse(
            true,
            'WhatsApp messages retrieved successfully for the workspace.',
            $messages
        );
    }

    public function getCampaigns(Request $request, Workspace $workspace)
    {
        // Get pagination parameters
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        // Extract filters from request
        $status = $request->get('status');
        $type = $request->get('type');
        $channelId = $request->get('channel_id');
        $createdFrom = $request->get('created_from'); // Start of the range
        $createdTo = $request->get('created_to');     // End of the range

        // Build the query dynamically
        $campaignsQuery = Campaign::with( ['campaignLists'])->where('workspace_id', $workspace->id);

        // Apply filters dynamically
        if ($status) {
            $campaignsQuery->where('status', $status);
        }

        if ($type) {
            $campaignsQuery->where('type', $type);
        }

        if ($channelId) {
            $campaignsQuery->where('channel_id', '=', $channelId);
        }

        if ($createdFrom && $createdTo) {
            $campaignsQuery->whereBetween('created_at', [$createdFrom, $createdTo]);
        } elseif ($createdFrom) {
            $campaignsQuery->where('created_at', '>=', $createdFrom);
        } elseif ($createdTo) {
            $campaignsQuery->where('created_at', '<=', $createdTo);
        }

        // Paginate the filtered results
        $campaigns = $campaignsQuery->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

        // Transform the campaigns
        $data = $campaigns->getCollection()->map(function ($campaign) {
            return new \App\Http\Responses\CampaignAnalytics($campaign);
        });

        // Set the transformed data back to the paginator
        $campaigns->setCollection($data);

        // Return a paginated response
        return $this->paginateResponse(
            true,
            'Campaigns retrieved successfully for the workspace.',
            $campaigns
        );
    }

    public function CamaignLists(Request $request,Workspace $workspace){
        $campaignsQuery = Campaign::where('workspace_id', $workspace->id)->orderBy('created_at','desc')->pluck('name','id');
          return $this->response(
            true,
            'WhatsApp analytics retrieved successfully.',
            $campaignsQuery
        );
    }
    public function getWhatsappAnalytics(Workspace $workspace)
    {

        if (!$workspace) {
            // Default counts if workspace is null
            return [
                'whatsapp_messages_count' => 0,
                'whatsapp_sent_message_count' => 0,
                'whatsapp_not_sent_message_count' => 0,
                'whatsapp_active_channels_count' => 0,
                'whatsapp_not_active_channels_count' => 0,
            ];
        }

        // Fetch total WhatsApp messages
        $whatsapp_messages_count = WhatsappMessage::whereHas('whatsappPhoneNumber.whatsappConfiguration.connector.channel', function ($query) use ($workspace) {
            $query->where('platform', Channel::WHATSAPP_PLATFORM)
                ->whereHas('workspaces', function ($workspaceQuery) use ($workspace) {
                    $workspaceQuery->where('workspaces.id', $workspace->id);
                });
        })->count();

        // Fetch sent messages count
        $whatsapp_sent_message_count = WhatsappMessage::whereHas('whatsappPhoneNumber.whatsappConfiguration.connector.channel', function ($query) use ($workspace) {
            $query->where('platform', Channel::WHATSAPP_PLATFORM)
                ->whereHas('workspaces', function ($workspaceQuery) use ($workspace) {
                    $workspaceQuery->where('workspaces.id', $workspace->id);
                });
        })->whereIn('status', [
            WhatsappMessage::MESSAGE_STATUS_SENT,
            WhatsappMessage::MESSAGE_STATUS_DELIVERED,
            WhatsappMessage::MESSAGE_STATUS_READ,
        ])->count();

        // Fetch not sent messages count
        $whatsapp_not_sent_message_count = WhatsappMessage::whereHas('whatsappPhoneNumber.whatsappConfiguration.connector.channel', function ($query) use ($workspace) {
            $query->where('platform', Channel::WHATSAPP_PLATFORM)
                ->whereHas('workspaces', function ($workspaceQuery) use ($workspace) {
                    $workspaceQuery->where('workspaces.id', $workspace->id);
                });
        })->whereNotIn('status', [
            WhatsappMessage::MESSAGE_STATUS_SENT,
            WhatsappMessage::MESSAGE_STATUS_DELIVERED,
            WhatsappMessage::MESSAGE_STATUS_READ,
        ])->count();

        // Fetch active channels count
        $whatsapp_active_channels_count = $workspace->channels()
            ->where('status', 'active')
            ->where('platform', Channel::WHATSAPP_PLATFORM)
            ->count();

        // Fetch inactive channels count
        $whatsapp_not_active_channels_count = $workspace->channels()
            ->where('status', '!=', 'active')
            ->where('platform', Channel::WHATSAPP_PLATFORM)
            ->count();

        $whatsapp_completed_campaigns_count = Campaign::where('workspace_id', $workspace->id)
            ->where('status', '=', CampaignStatus::COMPLETED)
            ->count();

        // Return the analytics data
       $analytics =  [
            'whatsapp_messages_count' => $whatsapp_messages_count,
            'whatsapp_sent_message_count' => $whatsapp_sent_message_count,
            'whatsapp_not_sent_message_count' => $whatsapp_not_sent_message_count,
            'whatsapp_active_channels_count' => $whatsapp_active_channels_count,
            'whatsapp_not_active_channels_count' => $whatsapp_not_active_channels_count,
           'whatsapp_completed_campaigns_count' => $whatsapp_completed_campaigns_count
        ];

        // Return the analytics as a response
        return $this->response(
            true,
            'WhatsApp analytics retrieved successfully.',
            $analytics
        );
    }

    public function getFailedTemplateMessages(Request $request, Workspace $workspace)
    {
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);
        $channelId = $request->get('channel_id');
        $campaignId = $request->get('campaign_id');
        $createdFrom = $this->convertTimestamp($request->get('created_from'));
        $createdTo = $this->convertTimestamp($request->get('created_to'));

        // Build the query for failed template messages
        $messagesQuery = WhatsappMessage::failedTemplateMessages()
            ->whereHas('campaign', function ($query) use ($workspace, $campaignId) {
                $query->where('workspace_id', $workspace->id);

                if ($campaignId) {
                    $query->where('id', $campaignId);
                }
            });

        // Filter by channel if provided
        if ($channelId) {
            $messagesQuery->whereHas('campaign', function ($query) use ($channelId) {
                $query->where('channel_id', $channelId);
            });
        }

        // Filter by date range
        if ($createdFrom && $createdTo) {
            $messagesQuery->whereBetween('created_at', [$createdFrom, $createdTo]);
        } elseif ($createdFrom) {
            $messagesQuery->where('created_at', '>=', $createdFrom);
        } elseif ($createdTo) {
            $messagesQuery->where('created_at', '<=', $createdTo);
        }

        // Paginate the results
        $messages = $messagesQuery->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        // Transform the messages
        $data = $messages->getCollection()->map(function ($message) {
            $failedStatus = $message->statuses->first();
            $error = $failedStatus?->errors->first();
            
            // Get recipient phone number
            $phoneNumber = null;
            if($message->recipient_type == WhatsappConsumerPhoneNumber::class){
                               $phoneNumber = $message->recipient?->phone_number;
            }elseif ($message->recipient_type === ContactEntity::class) {
                $phoneNumber = $message->recipient?->identifiers()
                    ->where('provider', 'whatsapp')
                    ->first()?->identifier;
            }

            return [
                'message_id' => $message->id,
                'campaign_id' => $message->campaign?->id,
                'campaign_name' => $message->campaign?->name,
                'channel_id' => $message->campaign?->channel?->id,
                'channel_name' => $message->campaign?->channel?->name,
                'phone_number' => $phoneNumber,
                'status' => $message->status,
                'failure_reason' => $error?->error_message ?? 'Unknown error',
                'error_code' => $error?->error_code,
                'error_title' => $error?->error_title,
                'error_details' => $error?->error_details,
                'failed_at' => $failedStatus?->timestamp ? date('Y-m-d H:i:s', $failedStatus->timestamp) : null,
                'created_at' => $message->created_at,
                'updated_at' => $message->updated_at,
            ];
        });

        // Set the transformed data back to the paginator
        $messages->setCollection($data);

        // Return a paginated response
        return $this->paginateResponse(
            true,
            'Failed template messages retrieved successfully.',
            $messages
        );
    }
}
