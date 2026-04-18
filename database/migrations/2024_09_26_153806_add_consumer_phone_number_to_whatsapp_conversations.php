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
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            // Add the foreign key to whatsapp_consumer_phone_numbers
            $table->unsignedBigInteger('whatsapp_consumer_phone_number_id')->nullable()->after('whatsapp_phone_number_id');

            // Add the foreign key constraint to the whatsapp_consumer_phone_numbers table
            $table->foreign('whatsapp_consumer_phone_number_id')
                ->references('id')
                ->on('whatsapp_consumer_phone_numbers')
                ->cascadeOnUpdate()
                ->nullOnDelete(); // On delete, make it nullable
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->dropForeign(['whatsapp_consumer_phone_number_id']);
            $table->dropColumn('whatsapp_consumer_phone_number_id');
        });
    }
};
