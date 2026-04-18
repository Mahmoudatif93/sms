<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\DefaultDreamsWhatsappRate
 *
 * Represents default WhatsApp rates for "Dreams" feature, with customizable rates for different categories.
 *
 * @property int $id
 * @property int $country_id Foreign key referencing the `country` table.
 * @property int $base_whatsapp_rate_id Foreign key referencing the `whatsapp_rates` table.
 * @property float|null $custom_marketing_rate Custom rate for marketing.
 * @property float|null $custom_utility_rate Custom rate for utility.
 * @property float|null $custom_authentication_rate Custom rate for authentication.
 * @property float|null $custom_authentication_international_rate Custom rate for international authentication.
 * @property float|null $custom_service_rate Custom rate for service.
 * @property Carbon|null $effective_date Date when the rate becomes active.
 * @property Carbon|null $expiry_date Date when the rate expires.
 * @property string|null $frequency Billing frequency (e.g., daily, weekly, etc.).
 * @property string $status Status of the rate (e.g., active, inactive).
 * @property Carbon|null $created_at Timestamp when the record was created.
 * @property Carbon|null $updated_at Timestamp when the record was last updated.
 *
 * @property-read Country $country The country associated with this rate.
 * @property-read WhatsappRate $baseRate The base WhatsApp rate this record customizes.
 *
 * @method static Builder|DefaultDreamsWhatsappRate newModelQuery()
 * @method static Builder|DefaultDreamsWhatsappRate newQuery()
 * @method static Builder|DefaultDreamsWhatsappRate query()
 * @method static Builder|DefaultDreamsWhatsappRate whereId($value)
 * @method static Builder|DefaultDreamsWhatsappRate whereCountryId($value)
 * @method static Builder|DefaultDreamsWhatsappRate whereBaseWhatsappRateId($value)
 * @method static Builder|DefaultDreamsWhatsappRate whereCustomMarketingRate($value)
 * @method static Builder|DefaultDreamsWhatsappRate whereCustomUtilityRate($value)
 * @method static Builder|DefaultDreamsWhatsappRate whereCustomAuthenticationRate($value)
 * @method static Builder|DefaultDreamsWhatsappRate whereCustomAuthenticationInternationalRate($value)
 * @method static Builder|DefaultDreamsWhatsappRate whereCustomServiceRate($value)
 * @method static Builder|DefaultDreamsWhatsappRate whereEffectiveDate($value)
 * @method static Builder|DefaultDreamsWhatsappRate whereExpiryDate($value)
 * @method static Builder|DefaultDreamsWhatsappRate whereFrequency($value)
 * @method static Builder|DefaultDreamsWhatsappRate whereStatus($value)
 * @method static Builder|DefaultDreamsWhatsappRate whereCreatedAt($value)
 * @method static Builder|DefaultDreamsWhatsappRate whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class DefaultDreamsWhatsappRate extends Model
{
    protected $table = 'default_dreams_whatsapp_rates';

    protected $fillable = [
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
     * Relationship to the Country model.
     *
     * @return BelongsTo
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    /**
     * Relationship to the Base WhatsApp Rate model.
     *
     * @return BelongsTo
     */
    public function baseRate(): BelongsTo
    {
        return $this->belongsTo(WhatsappRate::class, 'base_whatsapp_rate_id');
    }

    /**
     * Check if the rate is currently active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        $now = now();
        return $this->status === 'active' &&
            ($this->effective_date === null || $this->effective_date <= $now) &&
            ($this->expiry_date === null || $this->expiry_date >= $now);
    }
}
