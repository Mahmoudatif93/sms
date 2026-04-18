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
        Schema::table('sms_quotas', function (Blueprint $table) {
            $table->uuid('wallet_id')->after('user_id');
            $table->foreign('wallet_id')
            ->references('id')
            ->on('wallets')
            ->cascadeOnDelete()
            ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sms_quotas', function (Blueprint $table) {
            //
        });
    }
};
