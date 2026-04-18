<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * App\Models\MetaPricingMarket
 *
 * Represents a pricing market/region as defined by Meta for message pricing.
 *
 * @property int $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Collection|WorldCountry[] $countries
 * @property-read int|null $countries_count
 *
 * @method static Builder|MetaPricingMarket newModelQuery()
 * @method static Builder|MetaPricingMarket newQuery()
 * @method static Builder|MetaPricingMarket query()
 * @method static Builder|MetaPricingMarket whereId($value)
 * @method static Builder|MetaPricingMarket whereName($value)
 * @method static Builder|MetaPricingMarket whereCreatedAt($value)
 * @method static Builder|MetaPricingMarket whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class MetaPricingMarket extends Model
{
    /**
     * Meta’s official pricing market regions.
     */
    public const MARKETS = [
        'Countries',
        'North America',
        'Rest of Africa',
        'Rest of Asia Pacific',
        'Rest of Central & Eastern Europe',
        'Rest of Western Europe',
        'Rest of Latin America',
        'Rest of Middle East',
        'Other',
    ];
    protected $fillable = ['name'];

    /**
     * Seed all Meta pricing markets.
     */
    public static function seedDefaults(): void
    {
        foreach (self::MARKETS as $market) {
            self::firstOrCreate(['name' => $market]);
        }
    }

    /**
     * Relationship: All countries in this pricing market.
     *
     * @return HasMany
     */
    public function countries(): HasMany
    {
        return $this->hasMany(WorldCountry::class, 'meta_pricing_market_id');
    }
}
