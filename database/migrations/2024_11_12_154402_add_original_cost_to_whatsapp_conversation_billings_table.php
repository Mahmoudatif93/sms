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
        Schema::table('whatsapp_conversation_billings', function (Blueprint $table) {
            $table->decimal('original_cost', 10, 2)->nullable()->after('cost')->comment('USD');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_conversation_billings', function (Blueprint $table) {
            $table->dropColumn('original_cost');

        });
    }
};
