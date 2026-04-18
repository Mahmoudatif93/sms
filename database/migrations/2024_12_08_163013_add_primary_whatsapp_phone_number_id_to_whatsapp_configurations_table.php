<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('whatsapp_configurations', function (Blueprint $table) {
            $table->unsignedBigInteger('primary_whatsapp_phone_number_id')->nullable();
            $table->foreign('primary_whatsapp_phone_number_id')
                ->references('id')
                ->on('whatsapp_phone_numbers')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_configurations', function (Blueprint $table) {
            $table->dropForeign(['primary_whatsapp_phone_number_id']);
            $table->dropColumn('primary_whatsapp_phone_number_id');
        });
    }
};
