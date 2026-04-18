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
        Schema::table('campaigns', function (Blueprint $table) {
            // Add whatsapp_phone_number_id column as an unsignedBigInteger and allow it to be nullable
            $table->unsignedBigInteger('whatsapp_phone_number_id')->nullable()->after('status');

            // Add foreign key constraint to link with whatsapp_phone_numbers table
            $table->foreign('whatsapp_phone_number_id')
                ->references('id')
                ->on('whatsapp_phone_numbers')
                ->cascadeOnDelete(); // Optional: add cascade on delete if campaigns should delete with phone numbers

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['whatsapp_phone_number_id']);

            // Drop the whatsapp_phone_number_id column
            $table->dropColumn('whatsapp_phone_number_id');

        });
    }
};
