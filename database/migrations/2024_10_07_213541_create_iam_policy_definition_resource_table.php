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
        Schema::create('iam_policy_definition_resource', function (Blueprint $table) {
            $table->id();
            // Foreign key for iam_policy_definitions
            $table->foreignId('definition_id')
                ->constrained('iam_policy_definitions')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Foreign key for resources
            $table->foreignId('resource_id')
                ->constrained('resources')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('iam_policy_definition_resource', function (Blueprint $table) {
            $table->dropForeign(['definition_id']);
            $table->dropForeign(['resource_id']);
        });
        Schema::dropIfExists('iam_policy_definition_resource');
    }
};
