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
        Schema::table('whatsapp_reaction_messages', function (Blueprint $table) {
                  $table->enum('direction', ['SENT', 'RECEIVED'])->after('emoji')->default('RECEIVED');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_reaction_messages', function (Blueprint $table) {
            //
        });
    }
};
