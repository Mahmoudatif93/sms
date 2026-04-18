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
        Schema::create('iam_policy_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('iam_policy_id'); // Link to IAM policy
            $table->string('effect');  // 'allow' or 'deny'
            $table->string('actions');  // Actions this policy allows or denies (e.g., view, edit, delete)
            $table->timestamps();

            $table->foreign('iam_policy_id')
                ->references('id')
                ->on('iam_policies')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('iam_policy_definitions', function (Blueprint $table) {
            $table->dropForeign(['iam_policy_id']);
        });
        Schema::dropIfExists('iam_policy_definitions');
    }
};
