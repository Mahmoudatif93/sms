<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Service;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Service::firstOrCreate(
            ['name' => 'sms'],
            ['description' => 'sms']
        );
        Service::firstOrCreate(
            ['name' => 'other'],
            ['description' => 'whatsapp,hlr']
        );
    }
}
