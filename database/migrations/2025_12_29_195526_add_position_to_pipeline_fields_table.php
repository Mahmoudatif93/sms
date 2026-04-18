<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pipeline_fields', function (Blueprint $table) {
            $table->unsignedInteger('position')
                ->default(0)
                ->after('pipeline_tab_id');
        });
    }

    public function down(): void
    {
        Schema::table('pipeline_fields', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
