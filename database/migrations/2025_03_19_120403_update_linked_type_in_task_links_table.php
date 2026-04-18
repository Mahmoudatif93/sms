<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        DB::table('task_links')->where('linked_type', 'tasks')->update(['linked_type' => 'App\Models\Task']);
        DB::table('task_links')->where('linked_type', 'contacts')->update(['linked_type' => 'App\Models\ContactEntity']);
        DB::table('task_links')->where('linked_type', 'deals')->update(['linked_type' => 'App\Models\Deal']);
    }

    public function down()
    {
        DB::table('task_links')->where('linked_type', 'App\Models\Task')->update(['linked_type' => 'tasks']);
        DB::table('task_links')->where('linked_type', 'App\Models\ContactEntity')->update(['linked_type' => 'contacts']);
        DB::table('task_links')->where('linked_type', 'App\Models\Deal')->update(['linked_type' => 'deals']);
    }
};
