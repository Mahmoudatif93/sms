<?php

namespace App\Services;

use App\Jobs\SendWhatsAppBatchJob;
use App\Models\Campaign;
use App\Models\CampaignMessageLog;
use App\Models\ContactEntity;
use App\Traits\WhatsappTemplateManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Log;
use Throwable;

class PrepareCampaignMessagesServiceV2
{
    use WhatsappTemplateManager;

    // Batch size (Meta API allows a maximum of 50)
    const BATCH_SIZE = 50;

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

        // 1. Get list IDs
        $campaignListsIds = $campaign->lists()->pluck('lists.id')->toArray();

        // 2. Query contacts from lists
        $contactsQuery = DB::table('contacts')
            ->select('contacts.id as contact_id')
            ->join('contact_list', 'contacts.id', '=', 'contact_list.contact_id')
            ->whereIn('contact_list.list_id', $campaignListsIds)
            ->orderBy('contacts.id');

        $currentBatch = [];

        // 3. Chunk contacts
        $contactsQuery->chunkById(100, function (Collection $chunk) use ($campaign, $data, &$currentBatch) {

            foreach ($chunk as $row) {

                // Load contact with identifiers and attributes
                $contact = ContactEntity::with(['identifiers', 'attributes.attributeDefinition'])->find($row->contact_id);

                if (!$contact) {
                    Log::warning("Contact not found: {$row->contact_id}");
                    continue;
                }

                $phoneNumber = $contact->getPhoneIdentifier();

                if (!$phoneNumber) {
                    continue;
                }

                // Create a pending log entry
                $log = CampaignMessageLog::create([
                    'campaign_id' => $campaign->id,
                    'contact_id' => $contact->id,
                    'phone_number' => $phoneNumber,
                    'final_status' => $phoneNumber
                        ? CampaignMessageLog::STATUS_PENDING
                        : CampaignMessageLog::STATUS_SKIPPED,
                    'retry_count' => 0,
                ]);

                // بناء الـ components لهذا الـ contact
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

                // إضافة الرسالة إلى الـ batch الحالي
                $currentBatch[] = [
                    'campaignId' => $campaign->id,
                    'contactId' => $contact->id,
                    'phoneNumber' => $phoneNumber,
                    'fromPhoneNumberId' => $data['fromPhoneNumberId'],
                    'template' => $data['template'],
                    'accessToken' => $data['accessToken'],
                    'messageLogId' => $log->id,
                    'components' => $components,
                ];

                // إذا وصل الـ batch إلى الحد الأقصى، أرسله
                if (count($currentBatch) >= self::BATCH_SIZE) {
                    $this->dispatchBatch($currentBatch);
                    $currentBatch = []; // إعادة تعيين الـ batch
                }
            }
        },
            'contact_id'
        );

        // إرسال أي رسائل متبقية في الـ batch
        if (!empty($currentBatch)) {
            $this->dispatchBatch($currentBatch);
        }

        // Mark campaign complete when finished
        $campaign->update([
            'status' => Campaign::STATUS_COMPLETED,
        ]);
    }

    /**
     * إرسال batch من الرسائل
     */
    private function dispatchBatch(array $batch): void
    {
        Log::info('PrepareCampaignMessagesService: Dispatching batch', [
            'batch_size' => count($batch)
        ]);
        SendWhatsAppBatchJob::dispatch($batch)
            ->onQueue('whatsapp-campaigns');
    }
}
