<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class OrganizationInboxAgentSetting
 *
 * @property string $organization_id
 * @property string $automation_technique
 * @property int $wait_time_idle
 * @property int $max_conversations_per_agent
 * @property int $available_to_away_time
 * @property int $away_to_office_time
 * @property string $default_availability
 * @property bool $enable_auto_assign
 * @property int|null $auto_archive_delay
 * @property int|null $reassign_unresponsive_agents_after
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read \App\Models\Organization $organization
 */
class OrganizationInboxAgentSetting extends Model
{
    protected $primaryKey = 'organization_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'automation_technique',
        'wait_time_idle',
        'max_conversations_per_agent',
        'available_to_away_time',
        'away_to_office_time',
        'default_availability',
        'enable_auto_assign',
        'auto_archive_delay',
        'reassign_unresponsive_agents_after',
    ];

    protected $casts = [
        'enable_auto_assign' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->automation_technique ??= 'load_balancer';
            $model->wait_time_idle ??= 3600;
            $model->max_conversations_per_agent ??= 5;
            $model->available_to_away_time ??= 1800;
            $model->away_to_office_time ??= 3600;
            $model->default_availability ??= 'available';
            $model->enable_auto_assign ??= true;
            $model->auto_archive_delay ??= 60;
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
