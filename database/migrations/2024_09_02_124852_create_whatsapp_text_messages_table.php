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
        Schema::create('whatsapp_text_messages', function (Blueprint $table) {
            $table->id()->comment('');

            $table->string('whatsapp_message_id', 255)->comment('Foreign key to whatsapp_messages'); // Foreign key to whatsapp_messages

            // The 'body' field is required and will store the text of the message.
            // URLs must start with 'http://' or 'https://' if included.
            // Supports formatting: *bold*, _italics_, ~strike-through~, ```code```.
            $table->string('body', 4096)
                ->comment('The text of the message. URLs must start with "http://" or "https://". Max length: 4096 characters. Supports formatting: *bold*, _italics_, ~strike-through~, ```code```.');

            // The 'preview_url' field is optional and indicates whether a URL preview should be rendered.
            $table->boolean('preview_url')
                ->default(false)
                ->nullable()
                ->comment('Set to true to render a link preview for the first URL in the body text.');


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
        Schema::table('whatsapp_text_messages', function (Blueprint $table) {
            $table->dropForeign(['whatsapp_message_id']);
        });

        Schema::dropIfExists('whatsapp_text_messages');
    }
};
