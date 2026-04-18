<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;
use App\Models\WorldCountry;

/**
 * @OA\Schema(
 *     schema="Country",
 *     type="object",
 *     title="Country Response",
 *     required={"id", "name_en", "name_ar", "iso2", "iso3"},
 *     @OA\Property(property="id", type="integer", format="int64", description="Primary ID of the country"),
 *     @OA\Property(property="name_en", type="string", description="Country name in English"),
 *     @OA\Property(property="name_ar", type="string", description="Country name in Arabic"),
 *     @OA\Property(property="iso2", type="string", maxLength=2, description="ISO 3166-1 alpha-2 code"),
 *     @OA\Property(property="iso3", type="string", maxLength=3, description="ISO 3166-1 alpha-3 code"),
 *     @OA\Property(property="emoji", type="string", description="Emoji flag of the country")
 * )
 */
class Country extends DataInterface
{
    public int $id;
    public ?string $name_en;
    public string $name_ar;
    public string $iso2;
    public ?string $emoji;
    public ?string $market;
    public string $continent;

    /**
     * Country constructor.
     *
     * @param WorldCountry $country
     */
    public function __construct(WorldCountry $country)
    {
        $this->id = $country->id;
        $this->iso2 = $country->iso2;
        $this->emoji = $country->emoji ?? null;
        $this->name_en = $country->name_en ?? null;
        $this->market = $country->metaPricingMarket?->name;
        $this->continent = $country->continent;
    }
}
