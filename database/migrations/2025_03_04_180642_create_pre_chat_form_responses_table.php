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
        Schema::create('pre_chat_form_field_responses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('field_id');
            $table->uuid('conversation_id');
            $table->uuid('visitor_id');
            $table->text('value');
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('field_id')->references('id')->on('pre_chat_form_fields');
           
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pre_chat_form_responses');
    }
};
