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
            $table->timestamp('effective_date')->after('service'); // When this version starts
            $table->timestamp('expiry_date')->nullable()->after('effective_date'); // When this version ends (optional)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_rates', function (Blueprint $table) {
            $table->dropColumn(['effective_date', 'expiry_date']);
        });
    }
};
