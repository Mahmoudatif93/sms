<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Company Sender Service
 * 
 * Sends messages from Redis buffer for a specific company
 * Respects TPS (Transactions Per Second) limit for each company
 */
class WhatsAppCompanySenderService
{
    protected CampaignMessageService $messageService;

    public function __construct()
    {
        $this->messageService = app(CampaignMessageService::class);
    }

    /**
     * Send messages for a specific company respecting TPS limit
     * 
     * @param string $organizationId
     * @return bool True if message was sent, false if buffer is empty
     */
    public function sendForCompany(string $organizationId): bool
    {
        $redisKey = "wa:company:{$organizationId}:buffer";

        // Get TPS limit for this organization (default: 80 msg/sec)
        $tps = $this->getTpsForOrganization($organizationId);

        // Pop one message from the buffer (FIFO)
        $data = Redis::lpop($redisKey);

        if (!$data) {
            // No messages in buffer
            return false;
        }

        try {
            $payload = json_decode($data, true);

            if (!$payload) {
                Log::error("WhatsAppCompanySenderService: Invalid JSON payload", [
                    'organization_id' => $organizationId,
                    'data' => $data
                ]);
                return false;
            }

            // Send the message using existing service
            $this->sendMessageToMeta($payload);

            // Apply TPS delay (microseconds)
            // For 1 msg/sec: 1,000,000 / 80 = 12,500 microseconds = 12.5ms
            $delayMicroseconds = (int) (1_000_000 / $tps);
            usleep($delayMicroseconds);

            return true;

        } catch (\Throwable $e) {
            Log::error("WhatsAppCompanySenderService: Failed to send message", [
                'organization_id' => $organizationId,
                'error' => $e->getMessage(),
                'payload' => $payload ?? null
            ]);

            // Re-queue the message to the end of buffer for retry
            // Redis::rpush($redisKey, $data);

            return false;
        }
    }

    /**
     * Get TPS limit for organization
     */
    protected function getTpsForOrganization(string $organizationId): int
    {
        // You can store TPS in organization settings or use default
        // For now, using default 80 msg/sec for all organizations
        return 1;
    }

    /**
     * Send message to Meta WhatsApp API
     */
    protected function sendMessageToMeta(array $payload): bool
    {
        // Use existing CampaignMessageService to send
        return $this->messageService->send([
            'campaignId' => $payload['campaignId'],
            'contactId' => $payload['contactId'],
            'phoneNumber' => $payload['phoneNumber'],
            'fromPhoneNumberId' => $payload['fromPhoneNumberId'],
            'template' => $payload['template'],
            'accessToken' => $payload['accessToken'],
            'messageLogId' => $payload['messageLogId'],
            'jobId' => $payload['jobId'] ?? null,
            'existingMessageId' => $payload['existingMessageId'] ?? null
        ]);
    }

    /**
     * Get buffer size for organization
     */
    public function getBufferSize(string $organizationId): int
    {
        $redisKey = "wa:company:{$organizationId}:buffer";
        return (int) Redis::llen($redisKey);
    }

    /**
     * Get all organizations with pending messages
     */
    public function getOrganizationsWithPendingMessages(): array
    {
        $organizations = Organization::pluck('id')->toArray();
        $pending = [];

        foreach ($organizations as $orgId) {
            $size = $this->getBufferSize($orgId);
            if ($size > 0) {
                $pending[$orgId] = $size;
            }
        }

        return $pending;
    }
}

