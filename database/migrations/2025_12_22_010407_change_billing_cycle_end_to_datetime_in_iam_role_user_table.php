<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, convert existing timestamp values to datetime
        DB::statement('
            ALTER TABLE iam_role_user
            ADD COLUMN billing_cycle_end_new DATETIME NULL AFTER billing_frequency
        ');

        // Convert existing bigint timestamps to datetime
        DB::statement('
            UPDATE iam_role_user
            SET billing_cycle_end_new = FROM_UNIXTIME(billing_cycle_end)
            WHERE billing_cycle_end IS NOT NULL
        ');

        // Drop old column and rename new one
        Schema::table('iam_role_user', function (Blueprint $table) {
            $table->dropIndex('idx_billing_renewal');
            $table->dropColumn('billing_cycle_end');
        });

        DB::statement('
            ALTER TABLE iam_role_user
            CHANGE billing_cycle_end_new billing_cycle_end DATETIME NULL
        ');

        // Recreate index
        Schema::table('iam_role_user', function (Blueprint $table) {
            $table->index(['is_billing_active', 'billing_cycle_end'], 'idx_billing_renewal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert back to bigint timestamp
        DB::statement('
            ALTER TABLE iam_role_user
            ADD COLUMN billing_cycle_end_old BIGINT UNSIGNED NULL AFTER billing_frequency
        ');

        DB::statement('
            UPDATE iam_role_user
            SET billing_cycle_end_old = UNIX_TIMESTAMP(billing_cycle_end)
            WHERE billing_cycle_end IS NOT NULL
        ');

        Schema::table('iam_role_user', function (Blueprint $table) {
            $table->dropIndex('idx_billing_renewal');
            $table->dropColumn('billing_cycle_end');
        });

        DB::statement('
            ALTER TABLE iam_role_user
            CHANGE billing_cycle_end_old billing_cycle_end BIGINT UNSIGNED NULL
        ');

        Schema::table('iam_role_user', function (Blueprint $table) {
            $table->index(['is_billing_active', 'billing_cycle_end'], 'idx_billing_renewal');
        });
    }
};
