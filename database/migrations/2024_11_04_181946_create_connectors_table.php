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
        Schema::create('connectors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id'); // Foreign key to Workspace
            $table->string('name');
            $table->string('status')->default('pending');
            $table->string('region')->nullable();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('workspace_id')
                ->references('id')
                ->on('workspaces')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('connectors', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['workspace_id']);
        });
        Schema::dropIfExists('connectors');
    }
};
