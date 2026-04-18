<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->string('whatsapp_conversation_id', 255)->nullable()->after('status')->comment('The ID of the WhatsApp conversation this message belongs to.');

            // Add a foreign key constraint if applicable
            $table->foreign('whatsapp_conversation_id')
                ->references('id')
                ->on('whatsapp_conversations')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            // Drop the foreign key constraint if it was added
            $table->dropForeign(['whatsapp_conversation_id']);

        });

        Schema::dropIfExists('whatsapp_messages');
    }
};
