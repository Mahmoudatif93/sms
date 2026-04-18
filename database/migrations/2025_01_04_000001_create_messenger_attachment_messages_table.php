<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messenger_attachment_messages', function (Blueprint $table) {
            $table->id();
            $table->string('messenger_message_id');
            $table->string('type'); // image, video, audio, file
            $table->string('attachment_id')->nullable(); // Facebook reusable attachment ID
            $table->text('url')->nullable();
            $table->string('filename')->nullable();
            $table->text('caption')->nullable();
            $table->unsignedBigInteger('media_id')->nullable(); // Spatie media ID
            $table->timestamps();

            $table->foreign('messenger_message_id')
                ->references('id')
                ->on('messenger_messages')
                ->onDelete('cascade');

            $table->index('type');
            $table->index('attachment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messenger_attachment_messages');
    }
};
