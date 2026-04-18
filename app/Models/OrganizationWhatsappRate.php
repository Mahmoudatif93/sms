<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;


/**
 * App\Models\OrganizationWhatsappRate
 *
 * Represents a custom WhatsApp rate for a specific organization.
 *
 * @property int $id
 * @property string $organization_id
 * @property int $country_id
 * @property int $base_whatsapp_rate_id
 * @property float|null $custom_marketing_rate
 * @property float|null $custom_utility_rate
 * @property float|null $custom_authentication_rate
 * @property float|null $custom_authentication_international_rate
 * @property float|null $custom_service_rate
 * @property string|null $effective_date
 * @property string|null $expiry_date
 * @property string|null $frequency
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Organization $organization
 * @property-read Country $country
 * @property-read WhatsappRate $baseRate
 *
 * @method static Builder|OrganizationWhatsappRate newModelQuery()
 * @method static Builder|OrganizationWhatsappRate newQuery()
 * @method static Builder|OrganizationWhatsappRate query()
 *
 */
class OrganizationWhatsappRate extends Model
{
    protected $table = 'organization_whatsapp_rates'; // Table name for the pivot model

    protected $fillable = [
        'organization_id',
        'country_id',
        'base_whatsapp_rate_id',
        'custom_marketing_rate',
        'custom_utility_rate',
        'custom_authentication_rate',
        'custom_authentication_international_rate',
        'custom_service_rate',
        'effective_date',
        'expiry_date',
        'frequency',
        'status',
    ];

    protected $casts = [
        'effective_date' => 'datetime',
        'expiry_date' => 'datetime',
    ];

    /**
     * Relationship to the Organization model.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Relationship to the Country model.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Relationship to the Base WhatsApp Rate model.
     */
    public function baseRate(): BelongsTo
    {
        return $this->belongsTo(WhatsappRate::class, 'base_whatsapp_rate_id');
    }

    /**
     * Check if the rate is active based on the effective and expiry dates.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        $now = now();
        return $this->status === 'active' &&
            $this->effective_date <= $now &&
            ($this->expiry_date === null || $this->expiry_date >= $now);
    }
}
