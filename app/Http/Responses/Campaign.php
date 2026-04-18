<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;
use App\Enums\CampaignStatus;
use App\Models\Campaign as CampaignModel;
/**
 * @OA\Schema(
 *     schema="Campaign",
 *     type="object",
 *     title="Campaign Response",
 *     required={"id", "name", "type", "send_time_method", "workspace_id"},
 *     @OA\Property(property="id", type="string", description="UUID of the campaign"),
 *     @OA\Property(property="name", type="string", description="Name of the campaign"),
 *     @OA\Property(property="type", type="string", description="Type of the campaign"),
 *     @OA\Property(property="send_time_method", type="string", description="Method of sending the campaign"),
 *     @OA\Property(property="workspace_id", type="string", description="ID of the workspace the campaign belongs to"),
 *     @OA\Property(
 *         property="campaign_lists",
 *         type="array",
 *         @OA\Items(type="object", description="List of campaign lists associated with the campaign",
 *              @OA\Property(property="id", type="string", description="ID of the campaign list"),
 *              @OA\Property(property="name", type="string", description="Name of the campaign list")
 *         ),
 *         description="List of campaign lists"
 *     )
 * )
 */
class Campaign extends DataInterface
{
    public string $id;
    public string $name;
    public string $type;
    public string $send_time_method;
    public string $workspace_id;
    public array $campaign_lists;
    public  $send_time;
    public string $status;

    public  $created_at;
    public  $updated_at;
    public string $whatsapp_message_template_id;

    public string $template_name ;
    public $component;
    public int $from;

    public function __construct(CampaignModel $campaign)
    {
        $this->id = $campaign->id;
        $this->name = $campaign->name;
        $this->type =$campaign->type;// CampaignStatus::getKeyByValue($campaign->type);
        $this->send_time_method = $campaign->send_time_method;
        $this->workspace_id = $campaign->workspace_id;
        $this->whatsapp_message_template_id = $campaign->whatsapp_message_template_id;
        // Fetch and format campaign lists
        $this->campaign_lists = $campaign->campaignLists->map(function ($list) {
            return [
                'id' => $list->lists->id,
                'name' => $list->lists->name,
            ];
        })->toArray();
        $this->send_time = $campaign->send_time;
        $this->status = $campaign->status;
        $this->template_name = $campaign->whatsappMessageTemplate->name;
        $this->created_at = $campaign->created_at;
        $this->updated_at = $campaign->updated_at;
        $this->component = $campaign->getTemplateVariables();
        $this->from = $campaign->whatsappMessageTemplate->whatsapp_business_account_id;

    }
}
