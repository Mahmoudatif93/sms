<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;
use App\Models\CampaignMessageLog;
use App\Models\ContactEntity;

class CampaignContactLog extends DataInterface
{
    public string $contact_id;
    public ?string $phone_number;

    public ?string $final_status;
    public ?int $retry_count;

    public ?int $attempts_count;

    public mixed $log_id;


    public function __construct(ContactEntity $contact, string $campaignId)
    {
        // -------------------------------------------------------
        // CONTACT INFO
        // -------------------------------------------------------
        $this->contact_id = $contact->id;
        $this->phone_number = $contact->getPhoneIdentifier();

        $log = CampaignMessageLog::with('attempts')->where('contact_id', $contact->id)
            ->where('campaign_id', $campaignId)
            ->where('phone_number', $this->phone_number)
            ->first();

        // -------------------------------------------------------
        // LOG INFO
        // -------------------------------------------------------
        $this->final_status = $log?->final_status ?? null;
        $this->retry_count = $log?->retry_count ?? null;
        $this->attempts_count = $log?->attemptsCount() ?? null;
        $this->log_id = $log?->id ?? null;

    }
}
