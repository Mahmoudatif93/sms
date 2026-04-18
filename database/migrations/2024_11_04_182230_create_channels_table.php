<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('connector_id'); // Foreign key to Connector
            $table->uuid('workspace_id')->nullable();
            $table->string('name');
            $table->string('status')->default('inactive'); // Default status to inactive
            $table->string('platform'); // The platform (e.g., WhatsApp, SMS)
            $table->timestamps();

            // Foreign key constraint
            // $table->foreign('workspace_id')
            //     ->references('id')
            //     ->on('workspaces')
            //     ->cascadeOnDelete();

            $table->foreign('connector_id')
                ->references('id')
                ->on('connectors')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']);
            $table->dropForeign(['connector_id']);
        });
        Schema::dropIfExists('channels');
    }
};
