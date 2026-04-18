<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use Log;

/**
 * Dispatcher Job: Moves messages from FIFO queue to Redis buffers per company
 * 
 * This job pulls messages from the main FIFO queue and pushes them to
 * company-specific Redis buffers for parallel processing.
 */
class DispatchToCompanyBufferJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 3;

    public function __construct(
        public string $organizationId,
        public array $payload
    )
    {
    }

    public function handle()
    {
        try {
            // Redis key for this organization's buffer
            $redisKey = "wa:company:{$this->organizationId}:buffer";

            // Push message to the end of the buffer (FIFO within company)
            Redis::rpush($redisKey, json_encode($this->payload));

            Log::debug("DispatchToCompanyBufferJob: Message dispatched to buffer", [
                'organization_id' => $this->organizationId,
                'redis_key' => $redisKey,
                'buffer_size' => Redis::llen($redisKey)
            ]);

        } catch (\Throwable $e) {
            Log::error("DispatchToCompanyBufferJob: Failed to dispatch message", [
                'organization_id' => $this->organizationId,
                'error' => $e->getMessage(),
                'stack' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error("DispatchToCompanyBufferJob FAILED", [
            'organization_id' => $this->organizationId,
            'error' => $exception->getMessage()
        ]);
    }
}

