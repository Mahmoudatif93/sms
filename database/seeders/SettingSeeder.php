<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'category_en' => 'General',
                'category_ar' => 'عام',
                'name' => 'usd_to_sar_rate',
                'caption_en' => 'usd_to_sar_rate',
                'caption_ar' => 'سعر الدولار الامريكي مقابل الريال السعودي',
                'desc_en' => '',
                'desc_ar' => '',
                'value' => '3.76',
                'type' => 'text',
            ]
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['name' => $setting['name']],
                $setting
            );
        }
    }
}
