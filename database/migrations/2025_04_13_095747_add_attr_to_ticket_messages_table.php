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
        Schema::table('ticket_messages', function (Blueprint $table) {
            // $table->dropColumn('content');

            // Add the polymorphic relationship columns
            $table->string('messageable_type')->nullable()->after('sender_id');
            $table->uuid('messageable_id')->nullable()->after('messageable_type');

            // Add an index for better performance
            $table->index(['messageable_type', 'messageable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_messages', function (Blueprint $table) {
            //
        });
    }
};
