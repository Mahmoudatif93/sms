<?php

namespace App\Http\Responses;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Http\Interfaces\DataInterface;
use App\Models\CampaignMessageAttempt;
use App\Models\CampaignMessageLog;
use App\Models\WalletTransaction;
use App\Models\WhatsappMessage;

class CampaignAnalytics extends DataInterface
{
    public string $id;
    public string $name;
    public ?Channel $channel;
    public string $status;

    public int $total_contacts;
    public int $succeeded_messages;
    public int $meta_failed_messages;
    public int $attempt_failed_messages;
    public int $pending_messages;
    public int $skipped_contacts;
    public int $read_messages;

    public float $delivery_success_rate;
    public float $meta_failure_rate;
    public float $attempt_failure_rate;
    public float $read_rate;

    public string $total_billing;

    public $created_at;
    public $updated_at;

    public function __construct(\App\Models\Campaign $campaign)
    {
        $this->id = $campaign->id;
        $this->name = $campaign->name;
        $this->status = $campaign->status;
        $this->channel = $campaign->channel ? new Channel($campaign->channel) : null;

        // Fetch all logs for the campaign in one query
        $logs = CampaignMessageLog::where('campaign_id', $campaign->id)->get();

        $this->total_contacts = $campaign->getContacts()->count();
        $pendingLogs = $logs->where('final_status', CampaignMessageLog::STATUS_PENDING)->count();

// Count contacts that have NO log at all
        $contactsWithNoLog = $campaign
            ->contactsQuery()
            ->whereDoesntHave('campaignMessageLogs', function ($q) use ($campaign) {
                $q->where('campaign_id', $campaign->id);
            })
            ->count();


        $this->pending_messages = $pendingLogs + $contactsWithNoLog;
        $this->skipped_contacts = $logs->where('final_status', CampaignMessageLog::STATUS_SKIPPED)->count();

        // Meta failures (found inside WhatsappMessage status)
        $this->meta_failed_messages = WhatsappMessage::where('campaign_id', $campaign->id)
            ->where('status', WhatsappMessage::MESSAGE_STATUS_FAILED)
            ->count();

        // Attempt failures (job-level failures; found in attempts table)
        $this->attempt_failed_messages = CampaignMessageAttempt::whereIn('message_log_id', $logs->pluck('id'))
            ->where('status', CampaignMessageAttempt::STATUS_FAILED)
            ->count();

        // Delivered (succeeded)
        $this->succeeded_messages = WhatsappMessage::where('campaign_id', $campaign->id)
            ->distinct('recipient_id')
            ->count();

        // Read messages
        $this->read_messages = WhatsappMessage::where('campaign_id', $campaign->id)
            ->where('status', WhatsappMessage::MESSAGE_STATUS_READ)
            ->distinct('recipient_id')
            ->count();

        // Rates
        $total = max(1, $logs->count()); // avoid division by zero

        $this->delivery_success_rate = round(($this->succeeded_messages / $total) * 100, 2);
        $this->meta_failure_rate = round(($this->meta_failed_messages / $total) * 100, 2);
        $this->attempt_failure_rate = round(($this->attempt_failed_messages / $total) * 100, 2);
        $this->read_rate = round(($this->read_messages / $total) * 100, 2);

        // Billing
        $billingTotal = WalletTransaction::where('transaction_type', WalletTransactionType::USAGE)
            ->whereIn('status', [
                WalletTransactionStatus::ACTIVE,
                WalletTransactionStatus::PENDING,
            ])
            ->whereIn('meta->whatsapp_message_id', function ($q) use ($campaign) {
                $q->select('id')
                    ->from('whatsapp_messages')
                    ->where('campaign_id', $campaign->id);
            })
            ->sum('amount');

        $this->total_billing = number_format(abs($billingTotal), 5) . ' SAR';


        $this->created_at = $campaign->created_at;
        $this->updated_at = $campaign->updated_at;
    }
}
