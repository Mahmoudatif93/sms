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
        Schema::table('post_chat_form_field_responses', function (Blueprint $table) {
//            $table->foreign('conversation_id')->references('id')->on('conversations');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_chat_form_field_responses', function (Blueprint $table) {
//            $table->dropForeign(['conversation_id']);
        });
    }
};
