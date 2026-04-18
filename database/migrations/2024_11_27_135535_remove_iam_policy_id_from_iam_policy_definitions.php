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
        Schema::table('iam_policy_definitions', function (Blueprint $table) {
            // Drop the foreign key constraint if it exists
            if (Schema::hasColumn('iam_policy_definitions', 'iam_policy_id')) {
                $table->dropForeign(['iam_policy_id']);

                // Drop the column itself
                $table->dropColumn('iam_policy_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('iam_policy_definitions', function (Blueprint $table) {
            $table->unsignedBigInteger('iam_policy_id')->nullable();

            // Re-add the foreign key constraint
            $table->foreign('iam_policy_id')
                ->references('id')
                ->on('iam_policies')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }
};
