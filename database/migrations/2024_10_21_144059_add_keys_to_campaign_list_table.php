<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('campaign_list', function (Blueprint $table) {
            $table->foreign('campaign_id')
            ->references('id')
            ->on('campaigns')
            ->cascadeOnDelete()
            ->cascadeOnUpdate();

            $table->foreign('list_id')
            ->references('id')
            ->on('lists')
            ->cascadeOnDelete()
            ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_list', function (Blueprint $table) {
            //
        });
    }
};
