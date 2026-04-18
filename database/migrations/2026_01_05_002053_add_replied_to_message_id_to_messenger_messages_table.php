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
        Schema::table('messenger_messages', function (Blueprint $table) {
            $table->string('replied_to_message_id', 255)
                ->nullable()
                ->after('messenger_message_type')
                ->comment('The ID of the message being replied to.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messenger_messages', function (Blueprint $table) {
            $table->dropColumn('replied_to_message_id');
        });
    }
};
