<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\WorldCountry
 *
 * Represents a country entry in the reference table.
 *
 * @property int $id
 * @property string $name
 * @property string $name_en
 * @property string|null $name_ar
 * @property string|null $emoji
 * @property string $iso2
 * @property string $iso3
 * @property string|null $continent
 * @property int|null $meta_pricing_market_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read MetaPricingMarket|null $metaPricingMarket
 *
 * @method static Builder|WorldCountry whereId($value)
 * @method static Builder|WorldCountry whereName($value)
 * @method static Builder|WorldCountry whereNameEn($value)
 * @method static Builder|WorldCountry whereNameAr($value)
 * @method static Builder|WorldCountry whereEmoji($value)
 * @method static Builder|WorldCountry whereIso2($value)
 * @method static Builder|WorldCountry whereIso3($value)
 * @method static Builder|WorldCountry whereContinent($value)
 * @method static Builder|WorldCountry whereMetaPricingMarketId($value)
 * @method static Builder|WorldCountry create(array $attributes = [])
 * @method static Builder|WorldCountry query()
 * @method static Builder|WorldCountry newQuery()
 *
 * @mixin Eloquent
 */
class WorldCountry extends Model
{
    protected $table = 'world_countries';

    protected $fillable = [
        'name',
        'name_en',
        'name_ar',
        'emoji',
        'iso2',
        'iso3',
        'continent',
        'meta_pricing_market_id',
    ];

    /**
     * Relationship: the Meta pricing market this country belongs to.
     *
     * @return BelongsTo
     */
    public function metaPricingMarket(): BelongsTo
    {
        return $this->belongsTo(MetaPricingMarket::class, 'meta_pricing_market_id');
    }
}
