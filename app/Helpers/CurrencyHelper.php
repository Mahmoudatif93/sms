<?php
namespace App\Helpers;

use App\Models\Setting;
class CurrencyHelper
{

    public static function convertDollarToSAR($amountInUSD): float
    {
//        $rate = Setting::where('name', 'usd_to_sar_rate')->value('value');
//
//        if (!$rate) {
//            // Create the setting if it doesn't exist
//            $rate = 3.76;
//
//            Setting::create([
//                'category_en' => 'General',
//                'category_ar' => 'عام',
//                'name' => 'usd_to_sar_rate',
//                'caption_en' => 'usd_to_sar_rate',
//                'caption_ar' => 'سعر الدولار الامريكي مقابل الريال السعودي',
//                'desc_en' => '',
//                'desc_ar' => '',
//                'value' => $rate,
//                'type' => 'text',
//            ]);
//        }

        $rate = 3.76;

        $converted = $amountInUSD * floatval($rate);

        // Apply ceiling to 3 decimal places
        return ceil($converted * 1000) / 1000;
    }



}
