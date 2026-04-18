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
        Schema::create('workspace_channel', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->foreignUuid('workspace_id') // Connects to workspaces table
            ->constrained('workspaces')    // Explicit table name
            ->cascadeOnDelete()            // Deletes pivot rows when workspace is deleted
            ->cascadeOnUpdate();           // Updates pivot rows on workspace ID change

            $table->foreignUuid('channel_id')  // Connects to channels table
            ->constrained('channels')     // Explicit table name
            ->cascadeOnDelete()           // Deletes pivot rows when channel is deleted
            ->cascadeOnUpdate();          // Updates pivot rows on channel ID change

            $table->timestamps(); // Adds created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspace_channel', function (Blueprint $table) {
            // Drop foreign key constraints
            $table->dropForeign(['workspace_id']);
            $table->dropForeign(['channel_id']);
        });

        Schema::dropIfExists('workspace_channel');
    }
};
