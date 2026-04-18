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
        Schema::create('access_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();  // Primary key
            $table->uuid('organization_id');  // Organization ID
            $table->string('name');  // Access key name
            $table->enum('type', ['user', 'service'])->default('user');  // Type (user or service)
            $table->string('description')->nullable();  // Description of the access key
            $table->string('suffix');  // The short identifier for partial reference
            $table->text('token');  // The full token, stored securely
            $table->timestamp('last_used_at')->nullable();  // Last used timestamp
            $table->timestamps();  // created_at and updated_at timestamps

            // Foreign key constraint
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');  // Delete keys if the organization is deleted
        });

        // Create a pivot table for storing role references
        Schema::create('access_key_iam_role', function (Blueprint $table) {
            $table->uuid('access_key_id');  // Access key ID
            $table->unsignedBigInteger('iam_role_id');  // Role ID
            $table->string('type');  // Type of the role ('managed', 'organization', etc.)

            // Foreign key constraints
            $table->foreign('access_key_id')
                ->references('id')
                ->on('access_keys')
                ->onDelete('cascade');  // Delete role_refs if the access key is deleted
            $table->foreign('iam_role_id')
                ->references('id')
                ->on('iam_roles')
                ->onDelete('cascade');  // Delete role_refs if the role is deleted
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign keys in the pivot table
        Schema::table('access_key_iam_role', function (Blueprint $table) {
            $table->dropForeign(['access_key_id']);
            $table->dropForeign(['iam_role_id']);
        });

        // Drop the pivot table
        Schema::dropIfExists('access_key_iam_role');

        // Drop foreign key in the access_keys table
        Schema::table('access_keys', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
        });

        // Drop the access_keys table
        Schema::dropIfExists('access_keys');
    }
};
