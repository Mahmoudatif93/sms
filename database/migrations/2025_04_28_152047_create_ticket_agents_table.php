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
        Schema::create('ticket_agents', function (Blueprint $table) {
            $table->id();
            $table->uuid('ticket_id'); // Foreign key to tickets
            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');
            $table->integer('inbox_agent_id'); // Foreign key to users (inbox agents)
            $table->timestamp('assigned_at')->nullable(); // When the agent was assigned
            $table->timestamp('removed_at')->nullable(); // When the agent was removed
            $table->timestamps();
            $table->softDeletes(); // Soft deletes to track history
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_agents');
    }
};
