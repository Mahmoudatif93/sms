<?php

namespace App\Http\Responses;

use Maatwebsite\Excel\Concerns\ToArray;

class ChannelSender
{
    public string $id;
    // public string $workspaceId;
    public string $name;
    public string $status;
    public string $platform;
    public mixed $whatsappConfiguration;
    public mixed $phone_number;
    public mixed $sender_name;
    public mixed $connector;
    public ?int $createdAt;
    public ?int $updatedAt;

    public function __construct(\App\Models\Channel $channel)
    {
        $this->id = $channel->id;
        // $this->workspaceId = $channel->workspace_id;
        $this->name = $channel->name;
        $this->status = $channel->status;
        $this->platform = $channel->platform;
        $this->connector = $channel->connector;
        $this->whatsappConfiguration = $channel->connector->whatsappConfiguration;
        $this->phone_number = $channel->connector?->whatsappConfiguration?->primary_whatsapp_phone_number_id;
        $this->sender_name = $channel->connector?->SmsConfiguration?->Sender->name;
    }
}
