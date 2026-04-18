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
        Schema::table('iam_role_user', function (Blueprint $table) {
            $table->string('billing_frequency')->nullable()->after('organization_id');
            $table->unsignedBigInteger('billing_cycle_end')->nullable()->after('billing_frequency');
            $table->boolean('is_billing_active')->default(false)->after('billing_cycle_end');
            $table->uuid('wallet_id')->nullable()->after('is_billing_active');

            // Index for efficient querying of expired subscriptions
            $table->index(['is_billing_active', 'billing_cycle_end'], 'idx_billing_renewal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('iam_role_user', function (Blueprint $table) {
            $table->dropIndex('idx_billing_renewal');
            $table->dropColumn(['billing_frequency', 'billing_cycle_end', 'is_billing_active', 'wallet_id']);
        });
    }
};
