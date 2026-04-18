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
        Schema::create('iam_role_user', function (Blueprint $table) {
            $table->id();
            // Ensure user_id is unsignedBigInteger
            $table->integer('user_id');
            $table->unsignedBigInteger('iam_role_id');

            $table->foreign('user_id')->references('id')->on('user')->onDelete('cascade');
            $table->foreign('iam_role_id')->references('id')->on('iam_roles')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign keys before dropping the table
        Schema::table('iam_role_user', function (Blueprint $table) {
            $table->dropForeign(['user_id']);        // Drop foreign key for 'user_id'
            $table->dropForeign(['iam_role_id']);    // Drop foreign key for 'iam_role_id'
        });
        Schema::dropIfExists('iam_role_user');
    }
};
