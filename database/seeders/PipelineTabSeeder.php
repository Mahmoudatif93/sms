<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\PipelineTab;
use App\Models\Pipeline;
use App\Models\PipelineStage;

class PipelineTabSeeder extends Seeder
{
    public function run()
    {
        // 1. Retrieve or create a pipeline
        $pipeline = Pipeline::firstOrCreate(
            ['name' => 'Default Pipeline'], // Unique identifier
            [
                'id'          => (string) Str::uuid(),
                'description' => 'Sample pipeline for seeding tabs',
                'status'      => null,
                'assigned_to' => null,
                'color' => '#FF5733'
            ]
        );

        // 2. Create a pipeline tab associated with the pipeline
        $tabsData = [
            [
                'id'          => (string) Str::uuid(),
                'pipeline_id' => $pipeline->id,
                'name'        => 'General',
                'enabled' => true,
                'position'    => 1
            ]
        ];

        foreach ($tabsData as $tab) {
            PipelineTab::create($tab);
        }

        // 3. Create pipeline stages associated with the pipeline
        $stages = [
            ['name' => 'New', 'position' => 1],
            ['name' => 'In Progress', 'position' => 2],
            ['name' => 'Pending Approval', 'position' => 3],
            ['name' => 'Approved', 'position' => 4],
            ['name' => 'Closed', 'position' => 5],
        ];

        foreach ($stages as $stage) {
            PipelineStage::create([
                'id'          => (string) Str::uuid(),
                'pipeline_id' => $pipeline->id, // ✅ Correct variable
                'name'        => $stage['name'],
                'position'    => $stage['position'],
            ]);
        }
    }
}
