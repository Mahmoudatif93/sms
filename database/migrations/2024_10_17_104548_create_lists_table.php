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
        Schema::create('lists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->uuid('workspace_id');
            $table->enum('type', ['single', 'double']);
            $table->string('description')->nullable();
            $table->timestamps();
            $table->foreign('workspace_id')
            ->references('id')
            ->on('workspaces')
            ->cascadeOnDelete()
            ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lists', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['workspace_id']);
        });
        Schema::dropIfExists('lists');
    }
};
