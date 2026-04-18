<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    use SafeMigration;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->safeCreateTable('conversation_agents', function (Blueprint $table) {
            $table->id();
            $table->uuid('conversation_id'); // Foreign key to conversations
            $table->integer('inbox_agent_id'); // Foreign key to users (inbox agents)
            $table->timestamp('assigned_at')->nullable(); // When the agent was assigned
            $table->timestamp('removed_at')->nullable(); // When the agent was removed
            $table->timestamps();
            $table->softDeletes(); // Soft deletes to track history
        });

        // Safely add foreign key constraints
        $this->safeAddForeignKey('conversation_agents', 'conversation_id', 'conversations', 'id');
        $this->safeAddForeignKey('conversation_agents', 'inbox_agent_id', 'user', 'id');

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->safeDropForeignKey('conversation_agents', 'conversation_agents_conversation_id_fk');
        $this->safeDropForeignKey('conversation_agents', 'conversation_agents_inbox_agent_id_fk');
        $this->safeDropTable('conversation_agents');
    }
};
