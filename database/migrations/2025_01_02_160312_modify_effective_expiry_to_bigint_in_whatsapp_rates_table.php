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
        Schema::table('whatsapp_rates', function (Blueprint $table) {
            $table->integer('effective_date')->change(); // Store Unix timestamp
            $table->integer('expiry_date')->nullable()->change(); // Store Unix timestamp
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_rates', function (Blueprint $table) {
            $table->timestamp('effective_date')->change(); // Revert to DATETIME
            $table->timestamp('expiry_date')->nullable()->change(); // Revert to DATETIME
        });
    }
};
