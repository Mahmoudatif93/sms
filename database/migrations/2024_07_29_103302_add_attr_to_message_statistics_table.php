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
            $table->string('location_url')->nullable()->after('excle_file');
            $table->string('reminder_text')->nullable()->after('excle_file');
            $table->integer('reminder')->unsigned()->nullable()->after('excle_file');
            $table->timestamp('calendar_time')->nullable()->after('excle_file');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('message_statistics', function (Blueprint $table) {
            $table->dropColumn('location_url');
            $table->dropColumn('reminder_text');
            $table->dropColumn('reminder');
            $table->dropColumn('calendar_time');
        });
    }
};
