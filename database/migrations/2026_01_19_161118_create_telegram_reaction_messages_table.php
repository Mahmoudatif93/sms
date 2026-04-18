<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_reaction_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_message_id')->constrained()->cascadeOnDelete();
            $table->string('emoji');
            $table->string('direction'); // sent or received
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_reaction_messages');
    }
};
