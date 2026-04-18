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
        Schema::table('menus', function (Blueprint $table) {
            if (Schema::hasColumn('menus', 'sub_parent_id')) {  // Check if column exists

                $table->dropForeign(['sub_parent_id']);  // Drop foreign key if it exists
                // Drop the column
                $table->dropColumn('sub_parent_id');
            }


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->unsignedBigInteger('sub_parent_id')->nullable();  // Re-add the sub_parent_id column
            $table->foreign('sub_parent_id')->references('id')->on('menus')->onDelete('cascade');  // Re-add the foreign key constraint

        });
    }
};
