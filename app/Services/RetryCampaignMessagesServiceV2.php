<?php

namespace App\Services;

use App\Jobs\SendWhatsAppBatchJob;
use App\Models\Campaign;
use App\Models\CampaignMessageLog;
use App\Models\ContactEntity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Traits\WhatsappTemplateManager;
use Throwable;

class RetryCampaignMessagesServiceV2
{
    use WhatsappTemplateManager;
    // حجم الـ batch (Meta API تسمح بـ 50 كحد أقصى)
    const BATCH_SIZE = 50;

    public function retry(array $data): bool
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

        // 2. Query contacts
        $contactsQuery = DB::table('contacts')
            ->select('contacts.id as contact_id')
            ->join('contact_list', 'contacts.id', '=', 'contact_list.contact_id')
            ->whereIn('contact_list.list_id', $campaignListsIds)
            ->orderBy('contacts.id');

        // مصفوفة لتجميع الرسائل في batches
        $currentBatch = [];

        // 3. Chunk contacts
        $contactsQuery->chunkById(
            100,
            function (Collection $chunk) use ($campaign, $data, &$currentBatch) {

                foreach ($chunk as $row) {

                    $contact = ContactEntity::with('identifiers')->find($row->contact_id);

                    if (!$contact) {
                        Log::warning("Contact not found: {$row->contact_id}");
                        continue;
                    }

                    $phoneNumber = $contact->getPhoneIdentifier();
                    if (!$phoneNumber)
                        continue;

                    // Find existing log
                    $log = CampaignMessageLog::where('campaign_id', $campaign->id)
                        ->where('contact_id', $contact->id)
                        ->where('final_status', '!=', CampaignMessageLog::STATUS_SUCCEEDED)
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


                        $components = [];
                        if ($this->templateHasVariables($data['template']['template'])) {
                            $compiled = $campaign->compileTemplate($contact);
                            $componentsData = $this->validateAndBuildComponents(
                                $data['template']['template'],
                                $compiled
                            );

                            if ($componentsData['success']) {
                                $components = $componentsData['components'] ?? [];
                            } else {
                                Log::warning('Failed to build components for contact', [
                                    'contact_id' => $contact->id,
                                    'error' => $componentsData['error'] ?? 'Unknown error'
                                ]);
                            }
                        }
                        // إضافة الرسالة إلى الـ batch
                        $currentBatch[] = [
                            'campaignId' => $campaign->id,
                            'contactId' => $contact->id,
                            'phoneNumber' => $phoneNumber,
                            'fromPhoneNumberId' => $data['fromPhoneNumberId'],
                            'template' => $data['template'],
                            'accessToken' => $data['accessToken'],
                            'messageLogId' => $newLog->id,
                            'components' => [],
                        ];

                    } else {
                        // Reset and retry
                        $log->update([
                            'final_status' => CampaignMessageLog::STATUS_PENDING,
                            'retry_count' => $log->retry_count + 1,
                        ]);

                        // إضافة الرسالة إلى الـ batch
                        $currentBatch[] = [
                            'campaignId' => $campaign->id,
                            'contactId' => $contact->id,
                            'phoneNumber' => $phoneNumber,
                            'fromPhoneNumberId' => $data['fromPhoneNumberId'],
                            'template' => $data['template'],
                            'accessToken' => $data['accessToken'],
                            'messageLogId' => $log->id,
                            'components' => [],
                        ];
                    }

                    // إذا وصل الـ batch إلى الحد الأقصى، أرسله
                    if (count($currentBatch) >= self::BATCH_SIZE) {
                        $this->dispatchBatch($currentBatch);
                        $currentBatch = [];
                    }
                }
            },
            'contact_id'
        );

        // إرسال أي رسائل متبقية في الـ batch
        if (!empty($currentBatch)) {
            $this->dispatchBatch($currentBatch);
        }

        // Mark campaign completed
        $campaign->update([
            'status' => Campaign::STATUS_COMPLETED,
        ]);

        return true;
    }

    /**
     * إرسال batch من الرسائل
     */
    private function dispatchBatch(array $batch): void
    {
        Log::info('RetryCampaignMessagesService: Dispatching batch', [
            'batch_size' => count($batch)
        ]);

        SendWhatsAppBatchJob::dispatch($batch)
            ->onQueue('whatsapp-campaigns');
    }
}
