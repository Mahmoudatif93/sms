<?php

namespace Database\Seeders;

use App\Models\WorldCountry;
use Illuminate\Database\Seeder;

class WorldCountriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $this->command->info('🌍 Starting to seed world countries...');

        $countries = countries();

        $count = 0;

        foreach ($countries as $rinvexCountry) {

            $country = country($rinvexCountry['iso_3166_1_alpha2']);

            $name_en = $country->getName();
            $emoji = $country->getEmoji();
            $iso2 = $country->getIsoAlpha2();
            $iso3 = $country->getIsoAlpha3();
            $continent = $country->getContinent();

            WorldCountry::updateOrCreate(
                ['iso2' => $iso2],
                [
                    'name' => $name_en,
                    'name_en' => $name_en,
                    'emoji' => $emoji,
                    'iso3' => $iso3,
                    'continent' => $continent,
                ]
            );

            $this->command->line("✅ Seeded {$name_en} ({$iso2}) {$emoji}");
            $count++;
        }

        $this->command->info("🎉 Done! Seeded {$count} countries.");

    }
}
