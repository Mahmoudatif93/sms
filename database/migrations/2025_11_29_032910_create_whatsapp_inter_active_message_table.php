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
        Schema::create('whatsapp_interactive_messages', function (Blueprint $table) {
            $table->id();
            $table->string('whatsapp_message_id', 255)->comment('Foreign key to whatsapp_messages');

            // Interactive message type: button_reply, list_reply, nfm_reply, etc.
            $table->string('interactive_type', 50)->comment('Type of interactive message');

            // For button_reply
            $table->string('button_reply_id')->nullable()->comment('Button ID from button_reply');
            $table->string('button_reply_title')->nullable()->comment('Button title from button_reply');

            // For list_reply
            $table->string('list_reply_id')->nullable()->comment('List item ID from list_reply');
            $table->string('list_reply_title')->nullable()->comment('List item title from list_reply');
            $table->string('list_reply_description')->nullable()->comment('List item description from list_reply');

            // Store the full interactive payload as JSON for flexibility
            $table->json('payload')->nullable()->comment('Full interactive payload from webhook');

            $table->timestamps();

            // Foreign key to link this table with the 'whatsapp_messages' table.
            $table->foreign('whatsapp_message_id')
                ->references('id')
                ->on('whatsapp_messages')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Indexes for common queries
            $table->index('interactive_type');
            $table->index('button_reply_id');
            $table->index('list_reply_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_interactive_messages', function (Blueprint $table) {
            $table->dropForeign(['whatsapp_message_id']);
        });

        Schema::dropIfExists('whatsapp_interactive_messages');
    }
};
