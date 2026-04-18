<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chatbot_knowledge_base', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('channel_id');
            $table->string('category', 100)->nullable();
            $table->string('intent', 100);
            $table->text('keywords_text')->nullable();
            $table->json('keywords')->nullable();
            $table->text('question_ar')->nullable();
            $table->text('question_en')->nullable();
            $table->text('answer_ar')->nullable();
            $table->text('answer_en')->nullable();
            $table->boolean('may_need_handoff')->default(false);
            $table->boolean('requires_handoff')->default(false);
            $table->unsignedTinyInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('channel_id')
                ->references('id')
                ->on('channels')
                ->onDelete('cascade');

            $table->index(['channel_id', 'is_active']);
            $table->index(['channel_id', 'intent']);
        });

        // Add FULLTEXT index for MySQL
        DB::statement('ALTER TABLE chatbot_knowledge_base ADD FULLTEXT INDEX chatbot_knowledge_fulltext (question_ar, question_en, keywords_text)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chatbot_knowledge_base');
    }
};
