<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_sticker_messages', function (Blueprint $table) {
            $table->id();
            $table->string('whatsapp_message_id');
            $table->string('media_id');
            $table->boolean('is_animated')->default(false);
            $table->string('mime_type')->nullable();

            $table->timestamps();

            $table->foreign('whatsapp_message_id')
                ->references('id')
                ->on('whatsapp_messages')
                ->cascadeOnDelete();

            $table->index('media_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_sticker_messages');
    }
};
