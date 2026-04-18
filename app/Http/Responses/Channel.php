<?php

namespace App\Http\Responses;

use App\Models\Sender;

/**
 * @OA\Schema(
 *     schema="Channel",
 *     type="object",
 *     title="Channel Response",
 *     description="Channel resource representation",
 *     required={"id", "workspaceId", "name", "status", "platform"},
 *     @OA\Property(
 *         property="id",
 *         type="string",
 *         format="uuid",
 *         description="Channel ID",
 *         example="123e4567-e89b-12d3-a456-426614174000"
 *     ),
 *     @OA\Property(
 *         property="workspaceId",
 *         type="string",
 *         format="uuid",
 *         description="Workspace ID to which the channel belongs",
 *         example="123e4567-e89b-12d3-a456-426614174000"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Name of the channel",
 *         example="Channel Name"
 *     ),
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         description="Status of the channel",
 *         example="active"
 *     ),
 *     @OA\Property(
 *         property="platform",
 *         type="string",
 *         description="Platform associated with the channel",
 *         example="WhatsApp"
 *     ),
 *     @OA\Property(
 *         property="createdAt",
 *         type="integer",
 *         format="int64",
 *         nullable=true,
 *         description="Timestamp when the channel was created",
 *         example=1609459200
 *     ),
 *     @OA\Property(
 *         property="updatedAt",
 *         type="integer",
 *         format="int64",
 *         nullable=true,
 *         description="Timestamp when the channel was last updated",
 *         example=1609459200
 *     )
 * )
 */
class Channel
{
    public string $id;
    // public string $workspaceId;
    public string $name;
    public string $status;
    public string $platform;
    public string $created_at;
    public mixed $organization;
    public mixed $whatsappConfiguration;
    public mixed $phone_number;
    public mixed $sender_name;
    public mixed $connector;
    public mixed $profile_uri;
    public mixed $preChatForm;
    public mixed $postChatForm;
    public ?int $createdAt;
    public ?int $updatedAt;
    public ?int $gateway_id;
    public ?string $embedCode;
    public ?string $jsEmbedCode;
    public ?bool $shouldPay;
    public ?string $defaultWorkspaceId;
    public mixed $defaultWorkspace;


    public function __construct(\App\Models\Channel $channel)
    {
        $this->id = $channel->id;

        $this->name = $channel->name;
        $this->status = $channel->status;
        $this->platform = $channel->platform;
        $this->created_at = $channel->created_at;
        $this->connector = $channel->connector;
        $this->whatsappConfiguration = $channel->connector->whatsappConfiguration;
        $this->phone_number = $channel->connector?->whatsappConfiguration?->whatsappPhoneNumber?->display_phone_number;
        $this->profile_uri = $this->whatsappConfiguration?->whatsappPhoneNumber?->whatsappBusinessProfile?->profile_picture_url;
        $this->sender_name = $channel->connector?->SmsConfiguration?->Sender->name;
        $workspace = $channel->workspaces->first();
        if ($workspace) {
            $this->organization = [
                'id' => $workspace->organization->id,
                'name' => $workspace->organization->name
            ];
        }
        if ($channel->platform == "sms") {
            $this->status = $channel->connector?->SmsConfiguration?->Sender->status_text;
            $this->shouldPay = $channel->connector?->SmsConfiguration?->Sender->status == Sender::STATUS_WAITING_FOR_PAYMENT;
        }
        if ($channel->connector?->SmsConfiguration?->sender?->gateways) {
            $this->gateway_id = !empty($channel->connector->SmsConfiguration->sender->gateways->toArray()) ? $channel->connector->SmsConfiguration->sender->gateways[0]->id : null;
        }

        if ($channel->platform == \App\Models\Channel::TICKETING_PLATFORM) {
            $this->embedCode = $channel->connector?->ticketConfiguration?->ticketForm->getEmbedCode();
            $this->jsEmbedCode = $channel->connector?->ticketConfiguration?->ticketForm->getJsEmbedCode();
        }

        // Expose the default workspace if configured
        $this->defaultWorkspaceId = $channel->default_workspace_id ?? null;

        if ($channel->default_workspace_id) {
            $defaultWorkspace = $channel->workspaces
                ->firstWhere('id', $channel->default_workspace_id);

            if ($defaultWorkspace) {
                $this->defaultWorkspace = [
                    'id' => $defaultWorkspace->id,
                    'name' => $defaultWorkspace->name,
                ];
            }
        } else {
            $this->defaultWorkspace = null;
        }
    }
}
