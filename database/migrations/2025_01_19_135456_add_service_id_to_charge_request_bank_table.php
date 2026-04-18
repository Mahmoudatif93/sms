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
        Schema::table('charge_request_bank', function (Blueprint $table) {
            //
            $table->foreignId('service_id')->nullable()->constrained()->onDelete('cascade')->after('organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('charge_request_bank', function (Blueprint $table) {
            //
        });
    }
};
