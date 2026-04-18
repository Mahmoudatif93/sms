<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Services\ResendFailedWhatsappMessagesService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResendFailedWhatsappMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    protected Campaign $campaign;
    protected array $messageIds;
    protected string $fromPhoneNumberId;
    protected string $accessToken;
    protected array $template;

    /**
     * Create a new job instance.
     *
     * @param string $campaignId
     * @param array $messageIds
     * @param string $fromPhoneNumberId
     * @param string $accessToken
     * @param array $template
     */
    public function __construct(
        string $campaignId,
        array $messageIds,
        string $fromPhoneNumberId,
        string $accessToken,
        array $template
    ) {
        $this->campaign = Campaign::findOrFail($campaignId);
        $this->messageIds = $messageIds;
        $this->fromPhoneNumberId = $fromPhoneNumberId;
        $this->accessToken = $accessToken;
        $this->template = $template;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $service = app(ResendFailedWhatsappMessagesService::class);

            $result = $service->resend([
                'campaignId' => $this->campaign->id,
                'messageIds' => $this->messageIds,
                'fromPhoneNumberId' => $this->fromPhoneNumberId,
                'accessToken' => $this->accessToken,
                'template' => $this->template,
            ]);

            Log::info('ResendFailedWhatsappMessagesJob completed', [
                'campaign_id' => $this->campaign->id,
                'result' => $result,
            ]);
        } catch (Throwable $e) {
            Log::error('ResendFailedWhatsappMessagesJob exception', [
                'campaign_id' => $this->campaign->id,
                'message_ids' => $this->messageIds,
                'error' => $e->getMessage(),
                'stack' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('ResendFailedWhatsappMessagesJob FAILED', [
            'campaign_id' => $this->campaign->id,
            'message_ids' => $this->messageIds,
            'error' => $exception->getMessage(),
            'stack' => $exception->getTraceAsString(),
        ]);
    }
}
