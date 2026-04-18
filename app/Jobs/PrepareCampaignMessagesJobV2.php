<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Services\PrepareCampaignMessagesService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Log;
use Throwable;

class PrepareCampaignMessagesJobV2 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    protected $campaign;
    protected $fromPhoneNumberId;
    protected $accessToken;
    protected $template;

    public function __construct(
        $campaignId,
        $fromPhoneNumberId,
        $accessToken,
        $template
    )
    {
        $this->campaign = Campaign::findOrFail($campaignId);
        $this->fromPhoneNumberId = $fromPhoneNumberId;
        $this->accessToken = $accessToken;
        $this->template = $template;
    }

    public function handle()
    {
        try {

            $service = app(PrepareCampaignMessagesService::class);

            $service->prepare([
                'campaignId' => $this->campaign->id,
                'fromPhoneNumberId' => $this->fromPhoneNumberId,
                'accessToken' => $this->accessToken,
                'template' => $this->template,
            ]);

        } catch (Throwable $e) {

            Log::error("PrepareCampaignMessagesJob exception", [
                'campaign_id' => $this->campaign->id,
                'error' => $e->getMessage(),
                'stack' => $e->getTraceAsString()
            ]);

            Campaign::where('id', $this->campaign->id)
                ->update(['status' => Campaign::STATUS_FAILED]);

            throw $e;
        }

    }

    public function failed(Throwable $exception)
    {
        Log::error("PrepareCampaignMessagesJob FAILED", [
            'campaign_id' => $this->campaign->id,
            'error' => $exception->getMessage(),
            'stack' => $exception->getTraceAsString()
        ]);
    }
}
