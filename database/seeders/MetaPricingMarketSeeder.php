<?php

namespace Database\Seeders;

use App\Models\MetaPricingMarket;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MetaPricingMarketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌍 Seeding Meta Pricing Markets...');

        foreach (MetaPricingMarket::MARKETS as $market) {
            MetaPricingMarket::firstOrCreate(['name' => $market]);
            $this->command->line("✅ {$market}");
        }

        $this->command->info('✅ Done seeding Meta Pricing Markets.');
    }
}
