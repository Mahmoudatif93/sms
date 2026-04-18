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
        Schema::create('iam_policy_definition_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iam_policy_id')
                ->constrained('iam_policies')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('iam_policy_definition_id')
                ->constrained('iam_policy_definitions')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('iam_policy_definition_links', function (Blueprint $table) {
            $table->dropForeign(['iam_policy_id']);
            $table->dropForeign(['iam_policy_definition_id']);
        });
        Schema::dropIfExists('iam_policy_definition_links');
    }
};
