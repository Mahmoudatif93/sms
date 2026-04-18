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
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            // Add the polymorphic columns
            $table->unsignedBigInteger('messageable_id')->nullable();
            $table->string('messageable_type')->nullable();

            // Index for better performance
            $table->index(['messageable_id', 'messageable_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn(['messageable_id', 'messageable_type']);
        });
    }
};
