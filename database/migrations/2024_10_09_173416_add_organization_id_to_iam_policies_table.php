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
        Schema::table('iam_policies', function (Blueprint $table) {
            // Add the organization_id column
            $table->uuid('organization_id')->nullable()->after('id');

            // Set the foreign key constraint
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('iam_policies', function (Blueprint $table) {

            // Drop the foreign key constraint and the column
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');

        });
    }
};
