<?php

namespace App\Traits;

use App\Constants\Meta;
use App\Models\Organization;
use App\Models\Channel;
use App\Models\Workspace;
use App\Models\Connector;
use App\Models\SmsConfiguration;
use App\Models\Sender;

trait ChannelManager
{
    const TEST_CHANNEL_NAME = "Test SMS Channel";
    const TEST_CONNECTOR_NAME = "Test SMS Connector";
    const TEST_SENDER_NAME = "Dreams";
    use BusinessTokenManager;
    public function getChannelDetails(Channel $channel): \Illuminate\Http\JsonResponse|array
    {
        $connector = $channel->connector;

        // Retrieve the WhatsappConfiguration
        $whatsappConfiguration = $connector->whatsappConfiguration;
        $whatsappBusinessAccount = $whatsappConfiguration->whatsappBusinessAccount;
        $whatsappBusinessAccountID = $whatsappConfiguration->whatsapp_business_account_id;
        $phoneNumberID = $whatsappConfiguration->primary_whatsapp_phone_number_id;

        // Determine the access token
        if ($whatsappBusinessAccount->businessManagerAccount->name == 'Dreams Company') {
            $accessToken = Meta::ACCESS_TOKEN;
        } else {
            $accessToken = $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);

            if (!$accessToken) {
                return response()->json(['error' => 'Failed to get a valid access token'], 401);
            }
        }

        return [
            'whatsapp_business_account_id' => $whatsappBusinessAccountID,
            'phone_number_id' => $phoneNumberID,
            'access_token' => $accessToken
        ];
    }

    public function createDefaultSmsChannel(Workspace $workspace)
    {
        return \DB::transaction(function () use ($workspace) {
            $connector = Connector::create([
                'workspace_id' => $workspace->id,
                'name' => self::TEST_CONNECTOR_NAME,
                'status' => 'active',
                'region' => null,
            ]);
            $sender = Sender::find(0);
            $smsConfig = SmsConfiguration::create([
                'connector_id' => $connector->id,
                'sender_id' => $sender->id ?? 1,
                'status' => $sender->status ?? 1,
            ]);

            $channel = Channel::create([
                'connector_id' => $connector->id,
                'workspace_id' => $workspace->id,
                'name' => self::TEST_CHANNEL_NAME,
                'status' => 'active',
                'platform' => 'sms',
            ]);
            $channel->workspaces()->sync([$workspace->id]);
        });

    }

    public function removeDefaultChannelsInOrganization(Organization $organization): void
    {
        // Get all workspace IDs for the organization
        $workspaceIds = $organization->workspaces()->pluck('id')->toArray();

        // Find all default channels (by name) in these workspaces
        $defaultChannels = Channel::whereHas('workspaces', function ($query) use ($workspaceIds) {
            $query->whereIn('workspaces.id', $workspaceIds);
        })->where('name', ChannelManager::TEST_CHANNEL_NAME)
            ->get();

        foreach ($defaultChannels as $defaultChannel) {
            try {
                // Get the connector associated with the channel
                $connector = Connector::find($defaultChannel->connector_id);

                if ($connector) {
                    // Delete SMS configurations if they exist
                    SmsConfiguration::where('connector_id', $connector->id)->delete();

                    // Delete the connector
                    $connector->delete();
                }

                // Delete the channel
                $defaultChannel->delete();

            } catch (\Exception $e) {
                \Log::error('Error removing default channel: ' . $e->getMessage());
                throw $e;
            }
        }
    }

    public function getTestChannelName() {
        return self::TEST_CHANNEL_NAME;
    }

    private function deleteSender($id)
    {
        $sender = Sender::find( $id );
        if ($sender != null) {
            $sender->delete();
        } 
    }
}

