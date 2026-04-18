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
        Schema::table('balance_log', function (Blueprint $table) {
            $table->bigInteger('quota_id')->nullable();
            // $table->foreign('quota_id')->references('id')->on('sms_quota_users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('balance_log', function (Blueprint $table) {
            $table->dropColumn(['quota_id']);
        });
    }
};
