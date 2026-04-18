<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_message_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_message_id')->constrained()->cascadeOnDelete();
            $table->string('status'); // sent, delivered, read
            $table->unsignedBigInteger('timestamp');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_message_statuses');
    }
};
