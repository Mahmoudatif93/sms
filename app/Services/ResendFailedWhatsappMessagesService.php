<?php

namespace App\Services;

use App\Jobs\DispatchToCompanyBufferJob;
use App\Models\Campaign;
use App\Models\ContactEntity;
use App\Models\CampaignMessageLog;
use App\Models\WhatsappConsumerPhoneNumber;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Log;

class ResendFailedWhatsappMessagesService
{
    /**
     * Resend failed WhatsApp template messages.
     *
     * @param array $data
     * @return array
     */
    public function resend(array $data): array
    {
        /*
         * $data contains:
         * - campaignId
         * - messageIds (array of WhatsappMessage IDs to resend)
         * - fromPhoneNumberId
         * - accessToken
         * - template
         */

        $campaign = Campaign::findOrFail($data['campaignId']);
        $organizationId = $campaign->workspace?->organization_id;

        if (!$organizationId) {
            Log::warning('ResendFailedWhatsappMessagesService: No organization found', [
                'campaign_id' => $campaign->id,
            ]);
            return [
                'success' => false,
                'message' => 'Organization not found for campaign',
                'sent_count' => 0,
            ];
        }

        // Get template messages that are in 'initiated' status
        // (they were updated from 'failed' to 'initiated' in the controller before job dispatch)
        $messages = WhatsappMessage::whereIn('id', $data['messageIds'])
            ->where('type', WhatsappMessage::MESSAGE_TYPE_TEMPLATE)
            ->where('status', WhatsappMessage::MESSAGE_STATUS_INITIATED)
            ->where('campaign_id', $campaign->id)
            ->where('messageable_type', \App\Models\WhatsappTemplateMessage::class)
            ->with('recipient')
            ->get();

        if ($messages->isEmpty()) {
            Log::warning('ResendFailedWhatsappMessagesService: No valid messages found', [
                'campaign_id' => $campaign->id,
                'message_ids' => $data['messageIds'],
            ]);
            return [
                'success' => false,
                'message' => 'No valid messages found for resending (they may have already been processed)',
                'sent_count' => 0,
            ];
        }

        $sentCount = 0;
        $skippedCount = 0;

        // Process each message
        foreach ($messages as $message) {
            $recipient = $message->recipient;
            $phoneNumber = $recipient->phone_number;
            $contact = ContactEntity::where('organization_id', $organizationId)
                ->whereHas('identifiers', function ($query) use ($phoneNumber) {
                    $query->where('key', ContactEntity::IDENTIFIER_TYPE_PHONE)
                        ->where('value', $phoneNumber);
                })
                ->first();

            // Verify recipient is ContactEntity

            if (!$contact) {
                Log::warning('Recipient is not a ContactEntity', [
                    'message_id' => $message->id,
                    'recipient_type' => $message->recipient_type,
                ]);
                $skippedCount++;
                continue;
            }


            if (!$phoneNumber) {
                Log::warning('Phone number not found for contact', [
                    'message_id' => $message->id,
                    'contact_id' => $contact->id,
                ]);
                $skippedCount++;
                continue;
            }

            // Note: Message status is already updated to 'initiated' in the controller before job dispatch
            $messageLogId = CampaignMessageLog::where(['campaign_id'=>$campaign->id,'contact_id'=>$contact->id])->first();
            if(!$messageLogId){
                  Log::warning('message log not found for contact', [
                    'message_id' => $message->id,
                    'contact_id' => $contact->id,
                ]);
                $skippedCount++;
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
                'messageLogId' => $messageLogId->id, // No log needed for direct resend
                'existingMessageId' => $message->id, // Reference to existing message
            ];

            // Dispatch to company buffer
            DispatchToCompanyBufferJob::dispatch(
                organizationId: $organizationId,
                payload: $payload
            )->onQueue('dispatcher');

            $sentCount++;

            Log::info('Resending failed WhatsApp message', [
                'message_id' => $message->id,
                'campaign_id' => $campaign->id,
                'contact_id' => $contact->id,
                'phone_number' => $phoneNumber,
            ]);
        }

        return [
            'success' => true,
            'message' => 'Messages resent successfully',
            'sent_count' => $sentCount,
            'skipped_count' => $skippedCount,
            'total_requested' => count($data['messageIds']),
        ];
    }
}
