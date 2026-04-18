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
        Schema::create('chatbot_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('channel_id')->unique();
            $table->boolean('is_enabled')->default(false);
            $table->text('welcome_message_ar')->nullable();
            $table->text('welcome_message_en')->nullable();
            $table->text('fallback_message_ar')->nullable();
            $table->text('fallback_message_en')->nullable();
            $table->text('system_prompt')->nullable();
            $table->unsignedTinyInteger('handoff_threshold')->default(2);
            $table->json('handoff_keywords')->nullable();
            $table->string('ai_model', 50)->default('gpt-4o-mini');
            $table->unsignedSmallInteger('max_tokens')->default(300);
            $table->decimal('temperature', 2, 1)->default(0.3);
            $table->timestamps();

            $table->foreign('channel_id')
                ->references('id')
                ->on('channels')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chatbot_settings');
    }
};
