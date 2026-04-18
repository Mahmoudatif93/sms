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
        Schema::table('user_otp', function (Blueprint $table) {
            $table->integer('user_id')->nullable()->change();
            $table->char('organization_id',36)->nullable()->collation('utf8mb4_unicode_ci')->default(null)->after('user_id');
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_otp', function (Blueprint $table) {
            //
        });
    }
};
