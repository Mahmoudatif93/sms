<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignMessageAttempt;
use App\Models\CampaignMessageLog;
use App\Models\ContactEntity;
use App\Traits\ConversationManager;
use App\Traits\WhatsappMessageManager;
use App\Traits\WhatsappTemplateManager;
use App\Traits\WhatsappWalletManager;
use Exception;
use Throwable;

class CampaignMessageService
{
    use ConversationManager;
    use WhatsappWalletManager;
    use WhatsappMessageManager;
    use WhatsappTemplateManager;

    public function send(array $data): bool
    {
        /*
         * $data contains:
         * - campaignId
         * - contactId
         * - phoneNumber
         * - fromPhoneNumberId
         * - template
         * - accessToken
         * - messageLogId
         */

        // -------------------------------------------------------------
        // 0. Load fresh models
        // -------------------------------------------------------------
        $campaign = Campaign::with(['workspace', 'channel'])->findOrFail($data['campaignId']);
        $contact = ContactEntity::findOrFail($data['contactId']);
        $log = CampaignMessageLog::findOrFail($data['messageLogId']);

        // -------------------------------------------------------------
        // 1. Create attempt entry
        // -------------------------------------------------------------
        $attempt = CampaignMessageAttempt::create([
            'message_log_id' => $log->id,
            'status' => CampaignMessageAttempt::STATUS_DISPATCHED,
            'job_id' => $data['jobId'],
            'started_at' => now(),
        ]);

        try {

            // -------------------------------------------------------------
            // 2. Conversation
            // -------------------------------------------------------------
            $conversation = $this->startConversation(
                platform: $campaign->channel->platform,
                channel: $campaign->channel,
                contact: $contact,
            );

            // -------------------------------------------------------------
            // 3. Billing
            // -------------------------------------------------------------
            $transaction = $this->prepareWalletTransactionForTemplate(
                channel: $campaign->channel,
                conversation: $conversation,
                workspace: $campaign->workspace,
                contact: $contact,
                senderPhone: $data['phoneNumber'],
                templateId: $campaign->whatsapp_message_template_id,
            );

            // -------------------------------------------------------------
            // 4. Template variables
            // -------------------------------------------------------------
            $components = [];
            if ($this->templateHasVariables($data['template']['template'])) {
                $compiled = $campaign->compileTemplate($contact);
                $components = $this->validateAndBuildComponents(
                    $data['template']['template'],
                    $compiled
                );
            }

            // -------------------------------------------------------------
            // 5. Send Meta API request
            // -------------------------------------------------------------
            $response = $this->sendWhatsAppTemplateMessage(
                collect([
                    'language' => ['code' => $data['template']['template']['language']],
                    'to' => $data['phoneNumber'],
                    'from' => $data['fromPhoneNumberId'],
                ]),
                $data['accessToken'],
                $components['components'] ?? [],
                $data['template']['template']['name']
            );

            if (!$response['success']) {
                throw new Exception("Meta send error: " . json_encode($response));
            }

            // -------------------------------------------------------------
            // 6. Save local message
            // -------------------------------------------------------------
            $payload = collect([
                'language' => ['code' => $data['template']['template']['language']],
                'to' => $data['phoneNumber'],
                'from' => $data['fromPhoneNumberId'],
                'campaign_id' => $campaign->id,
            ]);
            
            if(isset($data['existingMessageId']) && $data['existingMessageId'] != null){
                $oldeMessage = \App\Models\WhatsappMessage::where('id',$data['existingMessageId'])->first();
                if($oldeMessage){
                    $oldeMessage->delete();
                }
            }
            $message = $this->saveTemplateMessageAndComponents(
                $payload,
                $response['data'],
                $components['components'] ?? null,
                $data['template']['template']
            );

            if ($transaction && $message) {
                $message->conversation_id = $conversation->id;
                $message->save();
                $message->updateWalletTransactionMeta($transaction->id);
            }

            // -------------------------------------------------------------
            // Mark success
            // -------------------------------------------------------------
            $attempt->update([
                'status' => CampaignMessageAttempt::STATUS_SUCCEEDED,
                'finished_at' => now(),
            ]);

            $log->update(['final_status' => CampaignMessageLog::STATUS_SUCCEEDED]);

            return true;

        } catch (Throwable $e) {

            // FAILED attempt
            $attempt->update([
                'status' => CampaignMessageAttempt::STATUS_FAILED,
                'exception_type' => get_class($e),
                'exception_message' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString(),
                'finished_at' => now(),
            ]);

            $log->update([
                'retry_count' => $log->retry_count + 1,
                'final_status' => CampaignMessageLog::STATUS_FAILED,
            ]);

            throw $e;
        }
    }
}
