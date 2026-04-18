<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon; 
class WhatsappRatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rates = [
            [
                'country_id' => 1,
                'currency' => 'USD',
                'marketing' => 0.0500,
                'utility' => 0.0300,
                'authentication' => 0.0200,
                'authentication_international' => 0.0250,
                'service' => 0.0150,
                'created_at' => Carbon::now(), 
                'updated_at' => Carbon::now(), 
            ],
            [
                'country_id' => 7,
                'currency' => 'RUB',
                'marketing' => 0.0450,
                'utility' => 0.0250,
                'authentication' => 0.0180,
                'authentication_international' => 0.0220,
                'service' => 0.0120,
                'created_at' => Carbon::now(), 
                'updated_at' => Carbon::now(), 
            ],
            [
                'country_id' => 20,
                'currency' => 'EGP',
                'marketing' => 0.0450,
                'utility' => 0.0250,
                'authentication' => 0.0180,
                'authentication_international' => 0.0220,
                'service' => 0.0120,
                'created_at' => Carbon::now(), 
                'updated_at' => Carbon::now(), 
            ],
            [
                'country_id' => 27,
                'currency' => 'ZAR',
                'marketing' => 0.0450,
                'utility' => 0.0250,
                'authentication' => 0.0180,
                'authentication_international' => 0.0220,
                'service' => 0.0120,
                'created_at' => Carbon::now(), 
                'updated_at' => Carbon::now(), 
            ],
            [
                'country_id' => 30,
                'currency' => 'EUR',
                'marketing' => 0.0450,
                'utility' => 0.0250,
                'authentication' => 0.0180,
                'authentication_international' => 0.0220,
                'service' => 0.0120,
                'created_at' => Carbon::now(), 
                'updated_at' => Carbon::now(), 
            ],
            [
                'country_id' => 31,
                'currency' => 'EUR',
                'marketing' => 0.0450,
                'utility' => 0.0250,
                'authentication' => 0.0180,
                'authentication_international' => 0.0220,
                'service' => 0.0120,
                'created_at' => Carbon::now(), 
                'updated_at' => Carbon::now(), 
            ],
            [
                'country_id' => 32,
                'currency' => 'EUR',
                'marketing' => 0.0450,
                'utility' => 0.0250,
                'authentication' => 0.0180,
                'authentication_international' => 0.0220,
                'service' => 0.0120,
                'created_at' => Carbon::now(), 
                'updated_at' => Carbon::now(), 
            ],
            [
                'country_id' => 33,
                'currency' => 'EUR',
                'marketing' => 0.0450,
                'utility' => 0.0250,
                'authentication' => 0.0180,
                'authentication_international' => 0.0220,
                'service' => 0.0120,
                'created_at' => Carbon::now(), 
                'updated_at' => Carbon::now(), 
            ],
            [
                'country_id' => 34,
                'currency' => 'EUR',
                'marketing' => 0.0450,
                'utility' => 0.0250,
                'authentication' => 0.0180,
                'authentication_international' => 0.0220,
                'service' => 0.0120,
                'created_at' => Carbon::now(), 
                'updated_at' => Carbon::now(), 
            ],
       
        ];
        
        DB::table('whatsapp_rates')->insert($rates);
    }
}
