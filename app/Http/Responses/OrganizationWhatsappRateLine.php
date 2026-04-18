<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;


class OrganizationWhatsappRateLine extends DataInterface
{
    public int $id;
    public string $organization_id;
    public ?float $custom_price;
    public int $created_at;
    public int $updated_at;

    // Optional preview fields
    public ?string $country_name = null;
    public ?string $category;
    public ?string $custom_currency;

    public ?float $base_price;
    public ?string $base_currency;
    public ?string $country_emoji = null;
    public ?string $pricing_model = null;

    public function __construct(\App\Models\OrganizationWhatsappRateLine $entry, bool $preview = false)
    {
        $this->id = $entry->id;
        $this->organization_id = $entry->organization_id;

        $rateLine = $entry->whatsappRateLine;

        $country = $rateLine->country;

        $this->country_emoji = $country?->emoji ?? null;
        $this->country_name = $country?->name_en;

        $this->category = $rateLine->category;

        $this->pricing_model = $rateLine->pricing_model;

        $this->base_price = $rateLine->price;
        $this->base_currency = $rateLine->currency;


        $this->custom_price = $entry->custom_price;
        $this->custom_currency = $entry->currency;

        $this->created_at = $entry->created_at->timestamp;
        $this->updated_at = $entry->updated_at->timestamp;

    }
}
