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
        Schema::table('message_statistics', function (Blueprint $table) {
            $table->tinyInteger('leng')->after('user_id');
            $table->float('count')->after('user_id');
            $table->float('cost')->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('message_statistics', function (Blueprint $table) {
            //
        });
    }
};
