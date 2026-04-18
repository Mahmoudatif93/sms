<?php

namespace App\Services;

use App\Jobs\ProcessCampaignChunkJob;
use App\Models\Campaign;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Log;

class PrepareCampaignMessagesService
{
    /**
     * Chunk size for processing contacts
     */
    private const CHUNK_SIZE = 500;

    public function prepare(array $data): void
    {
        /*
         * $data contains:
         * - campaignId
         * - fromPhoneNumberId
         * - accessToken
         * - template
         */

        $campaign = Campaign::findOrFail($data['campaignId']);
        $organizationId = $campaign->workspace?->organization_id;

        if (!$organizationId) {
            Log::warning("PrepareCampaignMessagesService: No organization found for campaign", [
                'campaign_id' => $campaign->id
            ]);
            return;
        }

        // 1. Get list IDs
        $campaignListsIds = $campaign->lists()->pluck('lists.id')->toArray();

        // 2. Query contacts from lists
        $contactsQuery = DB::table('contacts')
            ->select('contacts.id as contact_id')
            ->join('contact_list', 'contacts.id', '=', 'contact_list.contact_id')
            ->whereIn('contact_list.list_id', $campaignListsIds)
            ->orderBy('contacts.id');

        $chunkIndex = 0;

        // 3. Chunk contacts and dispatch a job for each chunk
        $contactsQuery->chunkById(
            self::CHUNK_SIZE,
            function (Collection $chunk) use ($campaign, $data, $organizationId, &$chunkIndex) {

                $contactIds = $chunk->pluck('contact_id')->toArray();

                // Dispatch a job for this chunk
                ProcessCampaignChunkJob::dispatch(
                    campaignId: $campaign->id,
                    organizationId: $organizationId,
                    contactIds: $contactIds,
                    fromPhoneNumberId: $data['fromPhoneNumberId'],
                    accessToken: $data['accessToken'],
                    template: $data['template'],
                    chunkIndex: $chunkIndex
                )->onQueue('campaign-chunks');

                Log::debug("PrepareCampaignMessagesService: Dispatched chunk job", [
                    'campaign_id' => $campaign->id,
                    'chunk_index' => $chunkIndex,
                    'contacts_count' => count($contactIds),
                ]);

                $chunkIndex++;

                // تحرير الذاكرة
                unset($contactIds);
            },
            'contact_id'
        );

        Log::info("PrepareCampaignMessagesService: All chunks dispatched", [
            'campaign_id' => $campaign->id,
            'total_chunks' => $chunkIndex,
        ]);

        // Mark campaign as completed (chunks are processing in background)
        $campaign->update([
            'status' => Campaign::STATUS_COMPLETED,
        ]);
    }
}

