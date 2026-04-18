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
        Schema::table('whatsapp_interactive_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('interactive_message_draft_id')->nullable()->after('interactive_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_interactive_messages', function (Blueprint $table) {
            $table->dropColumn('interactive_message_draft_id');
        });
    }
};
