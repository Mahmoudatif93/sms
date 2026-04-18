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
        Schema::create('whatsapp_reaction_messages', function (Blueprint $table) {
            $table->id()->comment('Primary key for the reaction message');

            $table->string('whatsapp_message_id', 255)->comment('Foreign key to whatsapp_messages'); // Foreign key to whatsapp_messages

            // The 'message_id' field stores the ID of the message being reacted to
            $table->string('message_id', 255)
                ->comment('The WhatsApp message ID that this reaction is responding to');

            // The 'emoji' field stores the emoji reaction (can be empty for removal)
            $table->string('emoji', 10)
                ->nullable()
                ->comment('The emoji used for the reaction. Empty string means the reaction was removed');

            $table->timestamps();

            // Foreign key to link this table with the 'whatsapp_messages' table.
            $table->foreign('whatsapp_message_id')
                ->references('id')
                ->on('whatsapp_messages')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_reaction_messages', function (Blueprint $table) {
            $table->dropForeign(['whatsapp_message_id']);
        });

        Schema::dropIfExists('whatsapp_reaction_messages');
    }
};
