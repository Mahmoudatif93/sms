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
        Schema::table('sms_quotas', function (Blueprint $table) {
            $table->timestamp('expire_date')->nullable()->after('available_points');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sms_quotas', function (Blueprint $table) {
            $table->dropForeign(['expire_date']);
        });
    }
};
