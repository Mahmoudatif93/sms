<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;


class Webhook extends DataInterface
{
    public ?string $id;
    public string $signing_key;
    public ?string $channel_id;
    public string $service;
    public string $event;
    public int $status;
    public int $is_active;
    public  string $created_at;
    public string $updated_at;

    public function __construct(\App\Models\Webhook $webhook)
    {
        $this->id = $webhook->id;
        $this->signing_key = $webhook->signing_key;
        $this->url = $webhook->url;
        $this->channel_id = $webhook->channel_id;
        $this->service = $webhook->service->name;
        $this->event = $webhook->event->name;
        $this->status = $webhook->is_active;
        $this->is_active = $webhook->is_active;
        $this->created_at = $webhook->created_at;
        $this->updated_at = $webhook->updated_at;

    }
}
