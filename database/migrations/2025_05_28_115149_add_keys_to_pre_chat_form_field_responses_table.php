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
        Schema::table('pre_chat_form_field_responses', function (Blueprint $table) {
            // $table->foreign('session_id')->references('id')->on('livechat_sessions');
//            $table->foreign('visitor_id')->references('id')->on('livechat_visitors');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pre_chat_form_field_responses', function (Blueprint $table) {
            // $table->dropForeign(['session_id']);
//            $table->dropForeign(['visitor_id']);
        });
    }
};
