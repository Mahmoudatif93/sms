<?php

namespace App\Jobs;

use App\Models\Campaign;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Log;
use Throwable;

/**
 * Process a chunk of contacts for a campaign
 * 
 * This job handles:
 * - Fetching phone identifiers for contacts
 * - Creating campaign message logs
 * - Dispatching messages to company buffers
 */
class ProcessCampaignChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

     public $timeout = 600; // 10 minutes per chunk
    public $tries = 1;

    public function __construct(
        public string $campaignId,
        public string $organizationId,
        public array $contactIds,
        public string $fromPhoneNumberId,
        public string $accessToken,
        public array $template,
        public int $chunkIndex = 0
    ) {
    }

    public function handle(): void
    {
        try {
            Log::debug("ProcessCampaignChunkJob: Processing chunk", [
                'campaign_id' => $this->campaignId,
                'chunk_index' => $this->chunkIndex,
                'contacts_count' => count($this->contactIds),
            ]);

            // Get phone identifiers for these contacts
            $identifiers = DB::table('identifiers')
                ->whereIn('contact_id', $this->contactIds)
                ->where('key', 'phone-number')
                ->pluck('value', 'contact_id')
                ->toArray();

            $logsBatch = [];

            // Build logs batch
            foreach ($this->contactIds as $contactId) {
                if (!isset($identifiers[$contactId])) {
                    continue;
                }
                $logsBatch[] = [
                    'campaign_id' => $this->campaignId,
                    'contact_id' => $contactId,
                    'phone_number' => $identifiers[$contactId],
                    'final_status' => 'pending',
                    'retry_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (empty($logsBatch)) {
                Log::warning("ProcessCampaignChunkJob: No valid contacts in chunk", [
                    'campaign_id' => $this->campaignId,
                    'chunk_index' => $this->chunkIndex,
                ]);
                return;
            }

            // Insert logs
            DB::table('campaign_message_logs')->insert($logsBatch);

            // Fetch inserted logs IDs
            $insertedLogs = DB::table('campaign_message_logs')
                ->where('campaign_id', $this->campaignId)
                ->whereIn('contact_id', $this->contactIds)
                ->pluck('id', 'contact_id')
                ->toArray();

            // Dispatch to company buffer
            foreach ($this->contactIds as $contactId) {
                if (!isset($insertedLogs[$contactId]) || !isset($identifiers[$contactId])) {
                    continue;
                }

                DispatchToCompanyBufferJob::dispatch(
                    $this->organizationId,
                    [
                        'campaignId' => $this->campaignId,
                        'contactId' => $contactId,
                        'phoneNumber' => $identifiers[$contactId],
                        'fromPhoneNumberId' => $this->fromPhoneNumberId,
                        'template' => $this->template,
                        'accessToken' => $this->accessToken,
                        'messageLogId' => $insertedLogs[$contactId],
                    ]
                )->onQueue('dispatcher');
            }

            Log::debug("ProcessCampaignChunkJob: Chunk processed successfully", [
                'campaign_id' => $this->campaignId,
                'chunk_index' => $this->chunkIndex,
                'dispatched_count' => count($insertedLogs),
            ]);

        } catch (Throwable $e) {
            Log::error("ProcessCampaignChunkJob: Failed to process chunk", [
                'campaign_id' => $this->campaignId,
                'chunk_index' => $this->chunkIndex,
                'error' => $e->getMessage(),
                'stack' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}

