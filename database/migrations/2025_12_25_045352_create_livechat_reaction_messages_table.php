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
        Schema::create('livechat_reaction_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('livechat_message_id')->constrained('livechat_messages')->cascadeOnDelete();
            $table->string('emoji');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('livechat_reaction_messages');
    }
};
