<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin Eloquent
 */


class CampaignMessageLog extends Model
{
    protected $table = 'campaign_message_logs';

    protected $fillable = [
        'campaign_id',
        'contact_id',
        'phone_number',
        'final_status',
        'retry_count',
    ];

    const STATUS_PENDING   = 'pending';
    const STATUS_SUCCEEDED = 'succeeded';
    const STATUS_FAILED    = 'failed';
    const STATUS_SKIPPED   = 'skipped';

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(ContactEntity::class, 'contact_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(CampaignMessageAttempt::class, 'message_log_id');
    }

    public function attemptsCount(): int
    {
        return $this->attempts()->count();
    }
}
