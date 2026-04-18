<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignMessageLog;
use App\Models\ContactEntity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RetryCampaignMessagesService
{
    public function retry(array $data):bool
    {
        /*
         * $data contains:
         * - campaignId
         * - fromPhoneNumberId
         * - accessToken
         * - template
         */

        $campaign = Campaign::findOrFail($data['campaignId']);

        // 1. Get list IDs
        $campaignListsIds = $campaign->lists()->pluck('lists.id')->toArray();
        
        $organizationId = $campaign->workspace?->organization_id;

        // 2. Query contacts
        $contactsQuery = DB::table('contacts')
            ->select('contacts.id as contact_id')
            ->join('contact_list', 'contacts.id', '=', 'contact_list.contact_id')
            ->whereIn('contact_list.list_id', $campaignListsIds)
            ->orderBy('contacts.id');

        // 3. Chunk contacts
        $contactsQuery->chunkById(
            20,
            function (Collection $chunk) use ($campaign, $data, $organizationId) {

                foreach ($chunk as $row) {

                    $contact = ContactEntity::with('identifiers')->find($row->contact_id);

                    if (!$contact) {
                        Log::warning("Contact not found: {$row->contact_id}");
                        continue;
                    }

                    $phoneNumber = $contact->getPhoneIdentifier();
                    if (!$phoneNumber) continue;

                    // Find existing log
                    $log = CampaignMessageLog::where('campaign_id', $campaign->id)
                        ->where('contact_id', $contact->id)
                        ->where('final_status' , '!=', CampaignMessageLog::STATUS_SUCCEEDED)
                        ->where('phone_number', $phoneNumber)
                        ->first();

                    // Send immediately if no log exists
                    if (!$log) {

                        $newLog = CampaignMessageLog::create([
                            'campaign_id' => $campaign->id,
                            'contact_id' => $contact->id,
                            'phone_number' => $phoneNumber,
                            'final_status' => CampaignMessageLog::STATUS_PENDING,
                            'retry_count' => 0,
                        ]);

                        // Get organization ID for buffer routing
                        $organizationId = $campaign->workspace?->organization_id;

                        if (!$organizationId) {
                            Log::warning("RetryCampaignMessagesService: No organization found", [
                                'campaign_id' => $campaign->id
                            ]);
                            continue;
                        }

                        // Prepare payload for dispatcher
                        $payload = [
                            'campaignId' => $campaign->id,
                            'contactId' => $contact->id,
                            'phoneNumber' => $phoneNumber,
                            'fromPhoneNumberId' => $data['fromPhoneNumberId'],
                            'template' => $data['template'],
                            'accessToken' => $data['accessToken'],
                            'messageLogId' => $newLog->id,
                        ];

                        // Dispatch to company buffer via Dispatcher Job
                        \App\Jobs\DispatchToCompanyBufferJob::dispatch(
                            organizationId: $organizationId,
                            payload: $payload
                        )->onQueue('dispatcher');

                    } else {
                        // Reset and retry
                        $log->update([
                            'final_status' => CampaignMessageLog::STATUS_PENDING,
                            'retry_count' => $log->retry_count + 1,
                        ]);

                        // Get organization ID for buffer routing

                        if (!$organizationId) {
                            Log::warning("RetryCampaignMessagesService: No organization found for retry", [
                                'campaign_id' => $campaign->id
                            ]);
                            continue;
                        }

                        // Prepare payload for dispatcher
                        $payload = [
                            'campaignId' => $campaign->id,
                            'contactId' => $contact->id,
                            'phoneNumber' => $phoneNumber,
                            'fromPhoneNumberId' => $data['fromPhoneNumberId'],
                            'template' => $data['template'],
                            'accessToken' => $data['accessToken'],
                            'messageLogId' => $log->id,
                        ];

                        // Dispatch to company buffer via Dispatcher Job
                        \App\Jobs\DispatchToCompanyBufferJob::dispatch(
                            organizationId: $organizationId,
                            payload: $payload
                        )->onQueue('dispatcher');
                    }
                }
            },
            'contact_id'
        );

        // Mark campaign completed
        $campaign->update([
            'status' => Campaign::STATUS_COMPLETED,
        ]);

        return true;
    }
}
