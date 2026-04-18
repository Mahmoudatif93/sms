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
        Schema::table('payment_sender', function (Blueprint $table) {
            $table->unsignedBigInteger('walletable_id')->nullable()->change();
            $table->string('walletable_type')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_sender', function (Blueprint $table) {
               // Revert the columns back to NOT NULL
               $table->unsignedBigInteger('walletable_id')->nullable(false)->change();
               $table->string('walletable_type')->nullable(false)->change();
        });
    }
};
