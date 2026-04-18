<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\OrganizationWhatsappExtra
 *
 * Represents additional WhatsApp quotas or extras for an organization.
 *
 * @property int $id
 * @property string $organization_id Foreign key to the `organizations` table
 * @property float $translation_quota Number of translations allowed
 * @property float $chatbot_quota Number of chatbot interactions allowed
 * @property float $hosting_quota Hosting storage quota
 * @property float $inbox_agent_quota Number of inbox agents allowed
 * @property bool $free_tier Indicates if free tier is enabled
 * @property float $free_tier_limit Limit of free tier conversations
 * @property string|null $frequency Billing frequency (e.g., monthly, yearly)
 * @property Carbon|null $effective_date When the quota becomes active
 * @property Carbon|null $expiry_date When the quota expires
 * @property bool $is_active Whether the quota is active
 */

class OrganizationWhatsappExtra extends Model
{
    protected $table = 'organization_whatsapp_extras';

    protected $fillable = [
        'organization_id',
        'translation_quota',
        'chatbot_quota',
        'hosting_quota',
        'inbox_agent_quota',
        'frequency',
        'effective_date',
        'expiry_date',
        'is_active',
        'free_tier',
        'free_tier_limit',
    ];

    protected $casts = [
        'translation_quota' => 'float',
        'chatbot_quota' => 'float',
        'hosting_quota' => 'float',
        'inbox_agent_quota' => 'float',
        'free_tier_limit' => 'float',
        'free_tier' => 'boolean',
        'is_active' => 'boolean',
        'effective_date' => 'timestamp',
        'expiry_date' => 'timestamp',
    ];

    /**
     * Relationship to the Organization model.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Check if the quota is currently active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        $now = now()->timestamp;
        return $this->is_active &&
            ($this->effective_date === null || $this->effective_date <= $now) &&
            ($this->expiry_date === null || $this->expiry_date >= $now);
    }
}
