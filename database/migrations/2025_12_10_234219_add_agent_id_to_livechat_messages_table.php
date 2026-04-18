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
            $table->integer('agent_id')->nullable()->after('sender_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('livechat_messages', function (Blueprint $table) {
            $table->dropColumn('agent_id');
        });
    }
};
