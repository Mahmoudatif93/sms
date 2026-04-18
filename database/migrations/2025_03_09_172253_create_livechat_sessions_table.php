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
        Schema::create('livechat_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('channel_id');
            $table->uuid('widget_id');
            $table->uuid('visitor_id');
            $table->integer('agent_id')->nullable();
            $table->string('status')->default('pending');
            $table->json('visitor_data')->nullable();
            $table->boolean('pre_chat_form_filled')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('channel_id')->references('id')->on('channels');
            $table->foreign('widget_id')->references('id')->on('widgets');
            $table->foreign('visitor_id')->references('id')->on('livechat_visitors');
            // Assuming you have a users table with uuid primary key
            $table->foreign('agent_id')->references('id')->on('user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('livechat_sessions');
    }
};
