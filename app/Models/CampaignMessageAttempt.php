<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignMessageAttempt extends Model
{
    protected $table = 'campaign_message_attempts';

    protected $fillable = [
        'message_log_id',
        'job_id',
        'status',
        'exception_type',
        'exception_message',
        'stack_trace',
        'started_at',
        'finished_at',
    ];

    const STATUS_DISPATCHED = 'dispatched';
    const STATUS_SUCCEEDED  = 'succeeded';
    const STATUS_FAILED     = 'failed';

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function messageLog(): BelongsTo
    {
        return $this->belongsTo(CampaignMessageLog::class, 'message_log_id');
    }
}
