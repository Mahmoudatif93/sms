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
        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('conversation_id');
            $table->string('telegram_message_id')->nullable();
            $table->string('chat_id');
            $table->string('type'); // text, image, video, audio, document, location

            $table->longText('content')->nullable(); // text or caption
            $table->json('payload')->nullable();     // full telegram response
            $table->string('file_id')->nullable();   // telegram file_id
            $table->string('file_path')->nullable(); // local or cloud path

            $table->string('status')->default('sent'); // sent, delivered, read, failed
            $table->boolean('from_agent')->default(false);

            $table->uuid('reply_to_message_id')->nullable();

            $table->timestamps();

            $table->foreign('conversation_id')
                ->references('id')
                ->on('conversations')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_messages');
    }
};
