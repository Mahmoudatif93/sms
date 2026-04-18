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
        Schema::create('livechat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('channel_id');
            $table->uuid('widget_id');
            $table->uuid('session_id');
            $table->string('sender_type');
            $table->string('sender_id');
            $table->string('type');
            $table->string('messageable_type');
            $table->uuid('messageable_id');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('channel_id')->references('id')->on('channels');
            $table->foreign('widget_id')->references('id')->on('widgets');
            $table->foreign('session_id')->references('id')->on('livechat_sessions');
            
            $table->index(['sender_type', 'sender_id']);
            $table->index(['messageable_type', 'messageable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('livechat_messages');
    }
};
