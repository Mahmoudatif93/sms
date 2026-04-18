<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->uuid('pipeline_stage_id')
                ->nullable()
                ->after('pipeline_id');

            $table->foreign('pipeline_stage_id')
                ->references('id')
                ->on('pipeline_stages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropForeign(['pipeline_stage_id']);
            $table->dropColumn('pipeline_stage_id');
        });
    }
};
