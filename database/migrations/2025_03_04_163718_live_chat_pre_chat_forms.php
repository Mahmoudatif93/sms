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
        Schema::create('pre_chat_forms', function (Blueprint $table) {
            $table->id();
            $table->uuid('widget_id')->nullable();
            $table->foreign('widget_id')->references('id')->on('widgets')->onDelete('cascade');
            $table->uuid('channel_id');
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
            $table->boolean('enabled')->default(true);
            $table->string('title')->default('Start Chat');
            $table->text('description')->nullable();
            $table->string('submit_button_text')->default('Start Chat');
            $table->boolean('require_fields')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pre_chat_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pre_chat_form_id')->constrained()->onDelete('cascade');
            $table->string('type'); // text,information, email, select, checkbox, etc.
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
        //
    }
};
