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
        Schema::create('iam_roles', function (Blueprint $table) {
            $table->id();
            $table->uuid('organization_id'); // Link to the organization
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['organization', 'managed'])->default('organization'); // Whether the role is org-based or platform-managed
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');
        });

        // Pivot table for IAM role and policies
        Schema::create('iam_role_policy', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('iam_role_id');
            $table->unsignedBigInteger('iam_policy_id');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('iam_role_id')
                ->references('id')
                ->on('iam_roles')
                ->onDelete('cascade');

            $table->foreign('iam_policy_id')
                ->references('id')
                ->on('iam_policies')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign keys in the pivot table 'iam_role_policy'
        Schema::table('iam_role_policy', function (Blueprint $table) {
            $table->dropForeign(['iam_role_id']);
            $table->dropForeign(['iam_policy_id']);
        });

        // Drop the 'iam_role_policy' table
        Schema::dropIfExists('iam_role_policy');

        // Drop foreign keys in 'iam_roles' table
        Schema::table('iam_roles', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
        });

        Schema::dropIfExists('iam_roles');
    }
};
