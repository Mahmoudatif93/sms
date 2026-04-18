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
        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Use UUID as the primary key
            $table->uuid('workspace_id'); // Reference to the workspace
            $table->timestamps(); // Created and updated timestamps

            // Foreign key to workspaces
            $table->foreign('workspace_id')
                ->references('id')
                ->on('workspaces')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key first before dropping the table
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']); // Drop the foreign key for 'workspace_id'
        });

        Schema::dropIfExists('contacts');
    }
};
