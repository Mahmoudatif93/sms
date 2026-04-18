<?php

namespace App\Http\Responses;

use App\Helpers\CurrencyHelper;
use App\Http\Interfaces\DataInterface;
use App\Models\WorldCountry;
use DB;

class WhatsappRateLineResponse extends DataInterface
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

    public ?int $country_id;
    public ?string $country_iso2;

    public array|\App\Models\WhatsappRateLine $rate;

    public array $test;

    public function __construct(array|\App\Models\WhatsappRateLine $whatsappRateLine)
    {
        if ($whatsappRateLine instanceof \App\Models\WhatsappRateLine) {
            $rateLine = $whatsappRateLine;

            $country = $rateLine->country ?? WorldCountry::find($rateLine->country_id);

            $this->id = $rateLine->id ?? $rateLine->rate_line_id;

            $this->pricing_model = $rateLine->pricing_model;

            $this->country_market = $country?->metaPricingMarket?->name;

            $this->country_emoji = $country?->emoji ?? null;
            $this->country_name = $country?->name_en;

            $this->country_iso2 = $country?->iso2;
            $this->country_id = $country?->id ?? null;

            $this->rate = $whatsappRateLine;

            $this->category = $rateLine->category;

            // ✨ Currency Conversion Logic
            if ($rateLine->currency === 'USD') {
                $converted = CurrencyHelper::convertDollarToSAR($rateLine->price);
                $this->price = ceil($converted * 1000) / 1000;
                $this->currency = 'SAR';
            } else {
                $this->price = $rateLine->price;
                $this->currency = $rateLine->currency;
            }

            $this->effective_date = $rateLine->effective_date;
            $this->expiry_date = $rateLine->expiry_date;
        } else {
            $rateLine = $whatsappRateLine;
            $country = $rateLine->country;
            $this->id = $rateLine->id;

            $this->pricing_model = $rateLine->pricing_model;

            $this->country_market = $country?->metaPricingMarket?->name;

            $this->country_emoji = $country?->emoji ?? null;
            $this->country_name = $country?->name_en;

            $this->country_iso2 = $country?->iso2;

            $this->country_id = $country?->id ?? null;

            $this->rate = $whatsappRateLine;


            $this->category = $rateLine->category;

            // ✨ Currency Conversion Logic
            if ($rateLine->currency === 'USD') {
                $converted = CurrencyHelper::convertDollarToSAR($rateLine->price);
                $this->price = ceil($converted * 1000) / 1000;
                $this->currency = 'SAR';
            } else {
                $this->price = $rateLine->price;
                $this->currency = $rateLine->currency;
            }

            $this->effective_date = $rateLine->effective_date;
            $this->expiry_date = $rateLine->expiry_date;
        }

        $worldCountry = DB::table('world_countries')->where('id', 20)->first();
        $rateLine = \App\Models\WhatsappRateLine::whereWorldCountryId($worldCountry->id)->get()->toArray();


        $this->test = [
            'world_country' => $worldCountry,
            'rate_lines' => $rateLine,
        ];
    }
}
