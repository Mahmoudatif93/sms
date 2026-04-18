<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignMessageAttempt;
use App\Models\CampaignMessageLog;
use App\Models\ContactEntity;
use App\Services\WhatsAppBatchService;
use App\Traits\ConversationManager;
use App\Traits\WhatsappMessageManager;
use App\Traits\WhatsappTemplateManager;
use App\Traits\WhatsappWalletManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWhatsAppBatchJob implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue, SerializesModels;
    use ConversationManager, WhatsappWalletManager, WhatsappMessageManager, WhatsappTemplateManager;

    public int $timeout = 600;
    public int $tries = 1;

    /**
     * @param array $batch مصفوفة من الرسائل للإرسال
     * كل عنصر يحتوي على:
     * - campaignId
     * - contactId
     * - phoneNumber
     * - fromPhoneNumberId
     * - template
     * - accessToken
     * - messageLogId
     */
    public function __construct(
        public array $batch
    ) {
    }

    /**
     * @throws Throwable
     */
    public function handle(): bool
    {
        try {
            Log::info('SendWhatsAppBatchJob: Starting batch send', [
                'batch_size' => count($this->batch)
            ]);
            // إرسال الـ batch عبر Meta API
            $batchService = app(WhatsAppBatchService::class);

            $batchResult = $batchService->sendBatch($this->batch);

            if (!$batchResult['success']) {
                Log::error('SendWhatsAppBatchJob: Batch send failed', [
                    'error' => $batchResult['error'] ?? 'Unknown error'
                ]);
                throw new \Exception($batchResult['error'] ?? 'Batch send failed');
            }

            // معالجة نتائج كل رسالة
            $results = $batchResult['results'];
            foreach ($results as $index => $result) {
                $this->processMessageResult($this->batch[$index], $result);
            }

            Log::info('SendWhatsAppBatchJob: Batch completed successfully', [
                'batch_size' => count($this->batch),
                'successful' => count(array_filter($results, fn($r) => $r['success'])),
                'failed' => count(array_filter($results, fn($r) => !$r['success']))
            ]);

            return true;

        } catch (Throwable $e) {
            Log::error('SendWhatsAppBatchJob: Exception occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // تحديث جميع الرسائل في الـ batch كفاشلة
            foreach ($this->batch as $message) {
                $this->markMessageAsFailed($message, $e);
            }

            throw $e;
        }
    }

    /**
     * معالجة نتيجة رسالة واحدة
     */
    private function processMessageResult(array $messageData, array $result): void
    {
        try {
            $campaign = Campaign::with(['workspace', 'channel'])->findOrFail($messageData['campaignId']);
            $contact = ContactEntity::findOrFail($messageData['contactId']);
            $log = CampaignMessageLog::findOrFail($messageData['messageLogId']);

            // إنشاء attempt entry
            $attempt = CampaignMessageAttempt::create([
                'message_log_id' => $log->id,
                'status' => CampaignMessageAttempt::STATUS_DISPATCHED,
                'job_id' => $this->job?->getJobId(),
                'started_at' => now(),
            ]);

            if ($result['success']) {
                // نجحت الرسالة
                $this->handleSuccessfulMessage($messageData, $result, $campaign, $contact, $log, $attempt);
            } else {
                // فشلت الرسالة
                $this->handleFailedMessage($messageData, $result, $log, $attempt);
            }

        } catch (Throwable $e) {
            Log::error('SendWhatsAppBatchJob: Error processing message result', [
                'message_log_id' => $messageData['messageLogId'] ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * معالجة رسالة ناجحة
     */
    private function handleSuccessfulMessage(
        array $messageData,
        array $result,
        $campaign,
        $contact,
        $log,
        $attempt
    ): void {
        try {
            // إنشاء المحادثة
            $conversation = $this->startConversation(
                platform: $campaign->channel->platform,
                channel: $campaign->channel,
                contact: $contact,
            );

            // إعداد معاملة المحفظة
            $transaction = $this->prepareWalletTransactionForTemplate(
                channel: $campaign->channel,
                conversation: $conversation,
                workspace: $campaign->workspace,
                contact: $contact,
                senderPhone: $messageData['phoneNumber'],
                templateId: $campaign->whatsapp_message_template_id,
            );

            // استخدام الـ components المُجهزة مسبقاً
            $components = $messageData['components'] ?? [];

            // حفظ الرسالة محلياً
            $payload = collect([
                'language' => ['code' => $messageData['template']['template']['language']],
                'to' => $messageData['phoneNumber'],
                'from' => $messageData['fromPhoneNumberId'],
                'campaign_id' => $campaign->id,
            ]);

            // استخراج بيانات الاستجابة من Meta
            $responseData = (object) [
                'messages' => [(object) ['id' => $result['data']['messages'][0]['id'] ?? uniqid()]],
                'contacts' => [(object) ['wa_id' => $result['data']['contacts'][0]['wa_id'] ?? $messageData['phoneNumber']]]
            ];

            $message = $this->saveTemplateMessageAndComponents(
                $payload,
                $responseData,
                $components,
                $messageData['template']['template']
            );

            if ($transaction && $message) {
                $message->conversation_id = $conversation->id;
                $message->save();
                $message->updateWalletTransactionMeta($transaction->id);
            }

            // تحديث الـ attempt كناجح
            $attempt->update([
                'status' => CampaignMessageAttempt::STATUS_SUCCEEDED,
                'finished_at' => now(),
            ]);

            // تحديث الـ log كناجح
            $log->update(['final_status' => CampaignMessageLog::STATUS_SUCCEEDED]);

        } catch (Throwable $e) {
            Log::error('SendWhatsAppBatchJob: Error handling successful message', [
                'message_log_id' => $log->id,
                'error' => $e->getMessage()
            ]);

            // تحديث كفاشل في حالة حدوث خطأ
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
        }
    }

    /**
     * معالجة رسالة فاشلة
     */
    private function handleFailedMessage(
        array $messageData,
        array $result,
        $log,
        $attempt
    ): void {
        $errorMessage = $result['data']['error']['message'] ?? 'Unknown error';

        $attempt->update([
            'status' => CampaignMessageAttempt::STATUS_FAILED,
            'exception_type' => 'MetaAPIError',
            'exception_message' => $errorMessage,
            'finished_at' => now(),
        ]);

        $log->update([
            'retry_count' => $log->retry_count + 1,
            'final_status' => CampaignMessageLog::STATUS_FAILED,
        ]);

        Log::warning('SendWhatsAppBatchJob: Message failed', [
            'message_log_id' => $log->id,
            'phone_number' => $messageData['phoneNumber'],
            'error' => $errorMessage
        ]);
    }

    /**
     * تحديد رسالة كفاشلة
     */
    private function markMessageAsFailed(array $messageData, Throwable $exception): void
    {
        try {
            $log = CampaignMessageLog::find($messageData['messageLogId']);

            if ($log) {
                CampaignMessageAttempt::create([
                    'message_log_id' => $log->id,
                    'status' => CampaignMessageAttempt::STATUS_FAILED,
                    'job_id' => $this->job?->getJobId(),
                    'exception_type' => get_class($exception),
                    'exception_message' => $exception->getMessage(),
                    'stack_trace' => $exception->getTraceAsString(),
                    'started_at' => now(),
                    'finished_at' => now(),
                ]);

                $log->update([
                    'retry_count' => $log->retry_count + 1,
                    'final_status' => CampaignMessageLog::STATUS_FAILED,
                ]);
            }
        } catch (Throwable $e) {
            Log::error('SendWhatsAppBatchJob: Error marking message as failed', [
                'message_log_id' => $messageData['messageLogId'] ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function failed(Throwable $exception)
    {
        Log::error('SendWhatsAppBatchJob FAILED', [
            'batch_size' => count($this->batch),
            'error' => $exception->getMessage()
        ]);
    }
}

