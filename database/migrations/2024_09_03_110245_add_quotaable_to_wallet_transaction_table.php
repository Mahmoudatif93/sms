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
        Schema::table('wallet_transaction', function (Blueprint $table) {
            Schema::table('wallet_transactions', function (Blueprint $table) {
                $table->unsignedBigInteger('quotaable_id')->nullable()->after('description');
                $table->string('quotaable_type')->nullable()->after('description');
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropColumn(['quotaable_id', 'quotaable_type']);
        });
    }
};
