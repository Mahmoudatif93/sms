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
        Schema::create('chatbot_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('chatbot_conversation_id');
            $table->text('user_message');
            $table->text('bot_response')->nullable();
            $table->uuid('knowledge_base_id')->nullable();
            $table->decimal('confidence_score', 3, 2)->nullable();
            $table->boolean('used_ai')->default(false);
            $table->unsignedInteger('tokens_used')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->string('language', 5)->default('ar');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('chatbot_conversation_id')
                ->references('id')
                ->on('chatbot_conversations')
                ->onDelete('cascade');

            $table->foreign('knowledge_base_id')
                ->references('id')
                ->on('chatbot_knowledge_base')
                ->onDelete('set null');

            $table->index('chatbot_conversation_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chatbot_messages');
    }
};
