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
        Schema::create('whatsapp_template_messages', function (Blueprint $table) {
            $table->id();
            $table->string('whatsapp_message_id', 255)->comment('Foreign key to whatsapp_messages'); // Foreign key to whatsapp_messages
            $table->unsignedBigInteger('whatsapp_template_id')->comment('Foreign key to whatsapp_templates');


            $table->timestamps();

            // Foreign key to link this table with the 'whatsapp_messages' table.
            $table->foreign('whatsapp_message_id')
                ->references('id')
                ->on('whatsapp_messages')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Foreign key to link this table with the 'whatsapp_message_templates' table.
            $table->foreign('whatsapp_template_id')
                ->references('id')
                ->on('whatsapp_message_templates')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_template_messages', function (Blueprint $table) {
            $table->dropForeign(['whatsapp_message_id']);
            $table->dropForeign(['whatsapp_template_id']);
        });

        Schema::dropIfExists('whatsapp_template_messages');
    }
};
