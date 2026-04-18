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
        Schema::create('post_chat_forms', function (Blueprint $table) {
            $table->id();
            $table->uuid('widget_id')->nullable();
            $table->foreign('widget_id')->references('id')->on('widgets')->onDelete('cascade');
            $table->uuid('channel_id');
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
            $table->boolean('enabled')->default(true);
            $table->string('title')->default('Chat Feedback');
            $table->text('description')->nullable();
            $table->string('submit_button_text')->default('Submit Feedback');
            $table->boolean('require_fields')->default(false);
            $table->integer('delay_seconds')->default(0); // Delay before showing the form
            $table->timestamps();
            $table->softDeletes();
        });

        // Create the table for post-chat form fields
        Schema::create('post_chat_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_chat_form_id')->constrained()->onDelete('cascade');
            $table->string('type'); // text, email, select, checkbox, rating, etc.
            $table->string('name');
            $table->string('label');
            $table->text('placeholder')->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('enabled')->default(true);
            $table->json('options')->nullable(); // For select, radio, checkboxes
            $table->json('validation')->nullable(); // Validation rules
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_chat_form_fields');
        Schema::dropIfExists('post_chat_forms');
    }
};
