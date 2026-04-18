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
        Schema::create('post_chat_form_field_responses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('field_id');
            $table->uuid('conversation_id');
            $table->uuid('contact_id');
            $table->text('value');
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('field_id')->references('id')->on('post_chat_form_fields');
            $table->foreign('contact_id')->references('id')->on('contacts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_chat_form_field_responses');
    }
};
