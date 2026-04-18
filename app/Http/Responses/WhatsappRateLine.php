<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;


class WhatsappRateLine extends DataInterface
{
    public int $id;
    public ?string $category;
    public ?float $price;
    public ?string $currency;
    public ?string $pricing_model;
    public int $effective_date;
    public ?int $expiry_date;

    public ?string $country_name;

    public ?string $country_emoji;

    public ?string $country_market;
    public ?string $country_iso2;


    public function __construct(\App\Models\WhatsappRateLine $rateLine)
    {
        $country = $rateLine->country;
        $this->id = $rateLine->id;

        $this->pricing_model = $rateLine->pricing_model;

        $this->country_market = $country?->metaPricingMarket?->name;

        $this->country_emoji = $country?->emoji ?? null;
        $this->country_name = $country?->name_en;

        $this->country_iso2 = $country?->iso2;


        $this->category = $rateLine->category;

        $this->price = $rateLine->price;
        $this->currency = $rateLine->currency;

        $this->effective_date = $rateLine->effective_date;
        $this->expiry_date = $rateLine->expiry_date;

    }
}
