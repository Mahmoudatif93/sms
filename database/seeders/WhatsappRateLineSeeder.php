<?php

namespace Database\Seeders;

use App\Constants\WhatsappPricingModel;
use App\Constants\WhatsappRateLines;
use App\Models\MetaPricingMarket;
use App\Models\WhatsappRateLine;
use App\Models\WorldCountry;
use Illuminate\Database\Seeder;

class WhatsappRateLineSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('📥 Seeding WhatsApp Rate Lines from Constants...');

        // Seed CBP rates
        $this->seedRates(WhatsappRateLines::MARKETING_RATES, 'marketing');
        $this->seedRates(WhatsappRateLines::UTILITY_RATES, 'utility');
        $this->seedRates(WhatsappRateLines::AUTHENTICATION_RATES, 'authentication');
        $this->seedRates(WhatsappRateLines::AUTHENTICATION_INTERNATIONAL_RATES, 'authentication_international');

        $this->seedMarketFallbackRates();

        // Seed PMP rates
        $this->seedPerMessageRates();
        $this->seedMarketFallbackPmpRates();

        $this->command->info('✅ Done seeding all WhatsApp rate lines.');
    }

    protected function seedRates(array $rates, string $category, string $pricingModel = WhatsappPricingModel::CBP): void
    {
        $this->command->info("➡️  Seeding {$pricingModel} rates for category: {$category}");

        foreach ($rates as $iso2 => [$price, $effective]) {
            $country = WorldCountry::query()->where('iso2', $iso2)->first();

            if (!$country) {
                $this->command->warn("⚠️  Country not found for ISO2 code: {$iso2} — skipping.");
                continue;
            }

            WhatsappRateLine::firstOrCreate([
                'world_country_id' => $country->id,
                'category' => $category,
                'effective_date' => $effective,
                'pricing_model' => $pricingModel,
                'price' => $price,
                'currency' => WhatsappRateLines::DEFAULT_CURRENCY,
            ]);

            $this->command->line("   🇨🇴 {$iso2} - {$category} ({$pricingModel}): \$" . number_format($price, 4) . " effective " . date('Y-m-d', $effective));
        }

        $this->command->info("✅ Done with {$pricingModel} rates for {$category}.\n");
    }

    protected function seedMarketFallbackRates(): void
    {
        $this->command->info("🌐 Seeding market-level fallback rates per country...");

        foreach (WhatsappRateLines::MARKET_FALLBACK_RATES as $marketName => $rateTypes) {
            $market = MetaPricingMarket::with('countries')->where('name', $marketName)->first();

            if (!$market) {
                $this->command->warn("⚠️  Market '{$marketName}' not found — skipping.");
                continue;
            }

            foreach ($market->countries as $country) {
                foreach ($rateTypes as $category => [$price, $effective]) {
                    WhatsappRateLine::firstOrCreate([
                        'world_country_id' => $country->id,
                        'category' => $category,
                        'effective_date' => $effective,
                        'pricing_model' => WhatsappPricingModel::CBP,
                        'price' => $price,
                        'currency' => WhatsappRateLines::DEFAULT_CURRENCY,
                    ]);

                    $this->command->line("   🌍 {$marketName} / {$country->iso2} / {$category}: \${$price} (effective " . date('Y-m-d', $effective) . ")");
                }
            }
        }

        $this->command->info("✅ Finished seeding market fallback rates by country.\n");
    }

    protected function seedPerMessageRates(): void
    {
        $this->command->info("📦 Seeding Per-Message Pricing (PMP) rates...");

        $this->seedRates(WhatsappRateLines::MARKETING_PMP_RATES, 'marketing', WhatsappPricingModel::PMP);
        $this->seedRates(WhatsappRateLines::UTILITY_PMP_RATES, 'utility', WhatsappPricingModel::PMP);
        $this->seedRates(WhatsappRateLines::AUTHENTICATION_PMP_RATES, 'authentication', WhatsappPricingModel::PMP);
        $this->seedRates(WhatsappRateLines::AUTHENTICATION_INTERNATIONAL_PMP_RATES, 'authentication_international', WhatsappPricingModel::PMP);

        $this->command->info("✅ Finished seeding all PMP rates.\n");
    }

    protected function seedMarketFallbackPmpRates(): void
    {
        $this->command->info("🌐 Seeding market-level fallback PMP rates per country...");

        foreach (WhatsappRateLines::MARKET_FALLBACK_PMP_RATES as $marketName => $rateTypes) {
            $market = MetaPricingMarket::with('countries')->where('name', $marketName)->first();

            if (!$market) {
                $this->command->warn("⚠️  Market '{$marketName}' not found — skipping.");
                continue;
            }

            foreach ($market->countries as $country) {
                foreach ($rateTypes as $category => [$price, $effective]) {
                    WhatsappRateLine::firstOrCreate([
                        'world_country_id' => $country->id,
                        'category' => $category,
                        'effective_date' => $effective,
                        'pricing_model' => WhatsappPricingModel::PMP,
                        'price' => $price,
                        'currency' => WhatsappRateLines::DEFAULT_CURRENCY,
                    ]);

                    $this->command->line("   🌍 {$marketName} / {$country->iso2} / {$category} (PMP): \${$price}");
                }
            }
        }

        $this->command->info("✅ Finished seeding market fallback PMP rates.\n");
    }

}
