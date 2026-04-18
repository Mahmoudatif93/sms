<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * App\Models\WhatsappRate
 *
 * Represents a versioned WhatsApp rate tied to a specific country.
 *
 * @property int $id Unique identifier for the rate.
 * @property int $country_id Foreign key to the Country model.
 * @property string $currency The currency of the rates (e.g., USD).
 * @property float|null $marketing Cost per message for marketing type.
 * @property float|null $utility Cost per message for utility type.
 * @property float|null $authentication Cost per message for authentication type.
 * @property float|null $authentication_international Cost per message for international authentication type.
 * @property float|null $service Cost per message for service type.
 * @property int $effective_date Unix timestamp for when the rate becomes active.
 * @property int|null $expiry_date Unix timestamp for when the rate expires (nullable for current rates).
 * @property Carbon|null $created_at Timestamp for when the record was created.
 * @property Carbon|null $updated_at Timestamp for when the record was last updated.
 *
 * @property-read Country $country The country this rate is associated with.
 *
 * @method static Builder|WhatsappRate activeOnDate(string|int $date)
 * @method static Builder|WhatsappRate whereCountryId($value)
 * @mixin Eloquent
 */
class WhatsappRate extends Model
{

    protected $fillable = [
        'country_id',
        'currency',
        'marketing',
        'utility',
        'authentication',
        'authentication_international',
        'service',
        'effective_date',
        'expiry_date',
    ];

    protected $casts = [
        'effective_date' => 'integer',
        'expiry_date' => 'integer',
    ];

    /**
     * Define a relationship with the Country model.
     *
     * @return BelongsTo
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Scope to filter rates active on a specific timestamp.
     *
     * @param Builder $query
     * @param string|int $date Date as a string or Unix timestamp.
     * @return Builder
     */
    public function scopeActiveOnDate($query, $date): Builder
    {
        $timestamp = is_numeric($date) ? $date : Carbon::parse($date)->timestamp;

        return $query->where('effective_date', '<=', $timestamp)
            ->where(function ($q) use ($timestamp) {
                $q->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>=', $timestamp);
            });
    }

    public function organizationRates(): HasMany
    {
        return $this->hasMany(OrganizationWhatsappRate::class, 'base_whatsapp_rate_id');
    }

}
