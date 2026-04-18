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
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            // Add context fields to track if this message is a reply to another message
            $table->string('replied_to_message_id', 255)
                ->nullable()
                ->after('status')
                ->comment('The ID of the message this message is replying to (from context.id)');
            
            $table->string('replied_to_message_from', 255)
                ->nullable()
                ->after('replied_to_message_id')
                ->comment('The phone number of the sender of the message being replied to (from context.from)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn(['replied_to_message_id', 'replied_to_message_from']);
        });
    }
};

