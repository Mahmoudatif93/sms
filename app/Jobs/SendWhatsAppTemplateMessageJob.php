<?php

namespace App\Jobs;

use App\Models\CampaignMessageAttempt;
use App\Services\CampaignMessageService;
use App\Traits\ConversationManager;
use App\Traits\WhatsappMessageManager;
use App\Traits\WhatsappTemplateManager;
use App\Traits\WhatsappWalletManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Log;
use Throwable;


class SendWhatsAppTemplateMessageJob implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue, SerializesModels,
        ConversationManager, WhatsappWalletManager, WhatsappMessageManager, WhatsappTemplateManager;

    public int $timeout = 600;  // sending takes longer
    public int $tries = 3;


    public function __construct(
        public string $campaignId,
        public string $contactId,
        public string $phoneNumber,
        public string $fromPhoneNumberId,
        public mixed  $template,
        public string $accessToken,
        public int    $messageLogId
    )
    {

    }

    /**
     * @throws Throwable
     */
    public function handle(): bool
    {
        $service = app(CampaignMessageService::class);

        return $service->send([
            'campaignId' => $this->campaignId,
            'contactId' => $this->contactId,
            'phoneNumber' => $this->phoneNumber,
            'fromPhoneNumberId' => $this->fromPhoneNumberId,
            'template' => $this->template,
            'accessToken' => $this->accessToken,
            'messageLogId' => $this->messageLogId,
            'jobId' => $this->job?->getJobId(),
        ]);
    }

    public function failed(Throwable $exception)
    {
        CampaignMessageAttempt::updateOrCreate(
            [
                'message_log_id' => $this->messageLogId,
                'job_id' => $this->job?->getJobId(),
            ],
            [
                'status' => CampaignMessageAttempt::STATUS_FAILED,
                'exception_type' => get_class($exception),
                'exception_message' => $exception->getMessage(),
                'stack_trace' => $exception->getTraceAsString(),
                'started_at' => now(),
                'finished_at' => now(),

            ]);

        Log::error("SendWhatsAppTemplateMessageJob FAILED", [
            'message_log_id' => $this->messageLogId,
            'error' => $exception->getMessage()
        ]);
    }
}
