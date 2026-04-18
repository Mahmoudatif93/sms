<?php

namespace Database\Seeders;

use App\Models\Board;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\BoardTab;
use App\Models\BoardStage;
class BoardSeeder extends Seeder
{
    public function run(): void
    {
       $board= Board::create(
        ['name' => 'Default Board'], // Unique identifier
        [
            'id'          => (string) Str::uuid(),
            'name' => 'Default Board',
            'assigned_to' => null,
            'color'=>'#FF5733'
        ]
        );



        // 2. Create a pipeline tab associated with the pipeline
        $tabsData = [
            [
                'id'          => (string) Str::uuid(),
                'board_id' => $board->id,
                 'name'        => 'General',
                'enabled' => true,
                'position'    => 1
            ]
        ];

        foreach ($tabsData as $tab) {
            BoardTab::create($tab);
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
            BoardStage::create([
                'id'          => (string) Str::uuid(),
                'board_id' => $board->id, // ✅ Correct variable
                'name'        => $stage['name'],
                'color'=>'#FF5733',
            ]);
        }
    }
}
