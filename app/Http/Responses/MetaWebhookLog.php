<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;


class MetaWebhookLog extends DataInterface
{
    public int $id;
    public bool $processed;
    public ?int $processed_at;
    public int $created_at;
    public int $updated_at;
    public array|string $payload;
    public ?string $payload_preview = null;

    public function __construct(\App\Models\MetaWebhookLog $log, bool $preview = false)
    {
        $this->id = $log->id;
        $this->processed = $log->processed;
        $this->processed_at = optional($log->processed_at)->timestamp;
        $this->created_at = $log->created_at->timestamp;
        $this->updated_at = $log->updated_at->timestamp;

        // Decode the payload if it’s JSON
        $decoded = is_array($log->payload)
            ? $log->payload
            : json_decode($log->payload, true);

        $this->payload = $decoded ?? $log->payload;

        if ($preview) {
            $this->payload_preview = str($log->payload)->limit(200)->toString();
        }
    }
}
