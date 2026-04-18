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
        Schema::create('resource_group_resource', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_group_id')->constrained('resource_groups')->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained('resources')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resource_group_resource', function (Blueprint $table) {
            $table->dropForeign(['resource_group_id']);
            $table->dropForeign(['resource_id']);
        });

        Schema::dropIfExists('resource_group_resource');
    }
};
