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
        Schema::create('whatsapp_location_messages', function (Blueprint $table) {
            $table->id()->comment('');

            $table->string('whatsapp_message_id', 255)->comment('Foreign key to whatsapp_messages'); // Foreign key to whatsapp_messages

            $table->string('longitude');
            $table->string('latitude');
            $table->string('name')->nullable();
            $table->string('address')->nullable();

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

        Schema::dropIfExists('whatsapp_location_messages');
    }
};
