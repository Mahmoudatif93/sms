<?php

namespace App\Http\Responses;

use Illuminate\Support\Collection;

class WhatsappRatesByRegion
{

    public string $market;
    /** @var OrganizationWhatsappRateLine[] */
    public array $rates;

    /**
     * @param string $market
     * @param Collection $rates
     */
    public function __construct(string $market, Collection $rates)
    {
        $this->market = $market;
        $this->rates = $rates->values()->all(); // convert collection to array
    }
}
