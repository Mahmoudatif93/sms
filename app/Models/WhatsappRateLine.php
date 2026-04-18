<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\WhatsappRateLine
 *
 * Represents a per-category WhatsApp rate for a country with effective date and pricing model.
 *
 * @property int $id
 * @property int $world_country_id
 * @property string $category
 * @property float|null $price
 * @property string $currency
 * @property string $pricing_model
 * @property int $effective_date
 * @property int|null $expiry_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read \App\Models\WorldCountry $country
 *
 * @method static \Illuminate\Database\Eloquent\Builder|WhatsappRateLine query()
 * @method static \Illuminate\Database\Eloquent\Builder|WhatsappRateLine whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhatsappRateLine whereWorldCountryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhatsappRateLine whereEffectiveDate($value)
 *
 * @mixin \Eloquent
 */
class WhatsappRateLine extends Model
{
    protected $fillable = [
        'world_country_id',
        'category',
        'price',
        'currency',
        'pricing_model',
        'effective_date',
        'expiry_date',
    ];

    protected $casts = [
        'price' => 'decimal:4',
        'effective_date' => 'integer',
        'expiry_date' => 'integer',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(WorldCountry::class, 'world_country_id');
    }
}
