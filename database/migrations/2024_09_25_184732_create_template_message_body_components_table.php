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
        Schema::create('template_message_body_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('template_message_id');
            $table->string('type');
            $table->timestamps();

            $table->foreign('template_message_id')
                ->references('id')
                ->on('whatsapp_template_messages')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_message_body_components', function (Blueprint $table) {
            $table->dropForeign(['template_message_id']);
        });

        Schema::dropIfExists('template_message_body_components');
    }
};
