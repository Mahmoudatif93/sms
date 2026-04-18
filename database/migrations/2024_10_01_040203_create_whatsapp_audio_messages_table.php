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
        Schema::create('whatsapp_audio_messages', function (Blueprint $table) {
            $table->id();
            $table->string('whatsapp_message_id', 255)->comment('Foreign key to whatsapp_messages'); // Foreign key to whatsapp_messages

            $table->string('media_id')->nullable(); // Media ID for uploaded media
            $table->string('link')->nullable(); // Media link for external URL
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
        // Check if the table exists before trying to drop the foreign key
        Schema::table('whatsapp_audio_messages', function (Blueprint $table) {
            $table->dropForeign(['whatsapp_message_id']); // Dropping the foreign key
        });

        Schema::dropIfExists('whatsapp_audio_messages');
    }
};
