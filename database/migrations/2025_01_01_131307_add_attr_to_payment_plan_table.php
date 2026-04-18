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
        Schema::table('payment_plan', function (Blueprint $table) {
            $table->char('workspace_id', 36)->nullable()->after('user_id')->collation('utf8mb4_unicode_ci');
            $table->char('organization_id',36)->nullable()->after('user_id')->collation('utf8mb4_unicode_ci');


            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');


            $table->foreign('workspace_id')
                ->references('id')
                ->on('workspaces')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_plan', function (Blueprint $table) {
            //
        });
    }
};
