<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;
use App\Models\CampaignMessageAttempt as CampaignMessageAttemptModel;

class CampaignMessageAttempt extends DataInterface
{
    public int $id;
    public string $status;
    public ?string $job_id;

    public ?string $exception_type;
    public mixed $exception_message;
    public mixed $stack_trace;

    public mixed $started_at;
    public mixed $finished_at;


    public function __construct(CampaignMessageAttemptModel $attempt)
    {
        $this->id          = $attempt->id;
        $this->status      = $attempt->status;
        $this->job_id      = $attempt->job_id;

        $this->exception_type    = $attempt->exception_type;
        $this->exception_message = $attempt->exception_message;
        $this->stack_trace       = $attempt->stack_trace;

        $this->started_at  = $attempt->started_at;
        $this->finished_at = $attempt->finished_at;

    }
}
