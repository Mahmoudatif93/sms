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
        Schema::table('livechat_messages', function (Blueprint $table) {
            $table->uuid('conversation_id')->nullable()->after('session_id');
            $table->foreign('conversation_id')->references('id')->on('conversations');
            $table->uuid('session_id')->nullable()->change();
        });
        Schema::table('livechat_messages', function (Blueprint $table) {
            $table->dropForeign(['session_id']);
            $table->dropColumn('session_id');
        });
      
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('livechat_messages', function (Blueprint $table) {
            //
        });
    }
};
