<?php

namespace Database\Seeders;

use App\Constants\MetaPricingMarketCountries;
use App\Models\MetaPricingMarket;
use App\Models\WorldCountry;
use Illuminate\Database\Seeder;

class MetaCountryMarketAssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->assignCountriesToMarket('Countries', MetaPricingMarketCountries::MARKET_COUNTRIES_ISO2_CODES);
        $this->assignCountriesToMarket('North America', MetaPricingMarketCountries::MARKET_NORTH_AMERICA_ISO2_CODES);
        $this->assignCountriesToMarket('Rest of Africa', MetaPricingMarketCountries::MARKET_REST_OF_AFRICA_ISO2_CODES);
        $this->assignCountriesToMarket('Rest of Asia Pacific', MetaPricingMarketCountries::MARKET_REST_OF_ASIA_PACIFIC_ISO2_CODES);
        $this->assignCountriesToMarket('Rest of Central & Eastern Europe', MetaPricingMarketCountries::MARKET_REST_OF_CENTRAL_EASTERN_EUROPE_ISO2_CODES);
        $this->assignCountriesToMarket('Rest of Western Europe', MetaPricingMarketCountries::MARKET_REST_OF_WESTERN_EUROPE_ISO2_CODES);
        $this->assignCountriesToMarket('Rest of Latin America', MetaPricingMarketCountries::MARKET_REST_OF_LATIN_AMERICA_ISO2_CODES);
        $this->assignCountriesToMarket('Rest of Middle East', MetaPricingMarketCountries::MARKET_REST_OF_MIDDLE_EAST_ISO2_CODES);
        $this->assignRemainingCountriesToMarket('Other');

    }


    protected function assignCountriesToMarket(string $marketName, array $iso2List): void
    {
        $this->command->info("🔗 Assigning countries to the '{$marketName}' pricing market...");

        $market = MetaPricingMarket::where('name', $marketName)->first();

        if (!$market) {
            $this->command->error("❌ Market '{$marketName}' not found. Make sure it's seeded.");
            return;
        }

        $assigned = 0;
        $skipped = 0;
        $missing = 0;

        $skippedCountries = [];
        $missingCountries = [];

        foreach ($iso2List as $iso2) {
            $country = WorldCountry::where('iso2', $iso2)->first();

            if (!$country) {
                $missing++;
                $missingCountries[] = $iso2;
                continue;
            }

            if ($country->meta_pricing_market_id === $market->id) {
                $skipped++;
                $skippedCountries[] = "{$country->name_en} ({$iso2})";
                continue;
            }

            $country->meta_pricing_market_id = $market->id;
            $country->save();

            $this->command->line("✅ Linked: {$country->name_en} ({$iso2})");
            $assigned++;
        }

        // Final summary
        $this->command->info("📊 Summary for '{$marketName}':");
        $this->command->info("✅ {$assigned} linked");
        $this->command->info("⏭️ {$skipped} skipped");
        $this->command->info("⚠️ {$missing} missing");

        // Detailed output
        if (!empty($skippedCountries)) {
            $this->command->line("⏭️ Skipped Countries:");
            foreach ($skippedCountries as $entry) {
                $this->command->line("- {$entry}");
            }
        }

        if (!empty($missingCountries)) {
            $this->command->line("⚠️ Missing ISO2 Codes:");
            foreach ($missingCountries as $iso2) {
                $this->command->line("- {$iso2}");
            }
        }
    }

    protected function assignRemainingCountriesToMarket(string $marketName): void
    {
        $this->command->info("🔎 Finding countries for '{$marketName}' market...");

        $market = MetaPricingMarket::where('name', $marketName)->first();

        if (!$market) {
            $this->command->error("❌ Market '{$marketName}' not found. Please seed it first.");
            return;
        }

        $excludedIso2s = MetaPricingMarketCountries::allAssignedIso2Codes();

        $countries = WorldCountry::whereNotIn('iso2', $excludedIso2s)->get();

        $assigned = 0;
        foreach ($countries as $country) {
            if ($country->meta_pricing_market_id !== $market->id) {
                $country->meta_pricing_market_id = $market->id;
                $country->save();
                $this->command->line("✅ Linked {$country->name_en} ({$country->iso2})");
                $assigned++;
            }
        }

        $this->command->info("🎉 Assigned {$assigned} countries to '{$marketName}' market.");
    }


}
