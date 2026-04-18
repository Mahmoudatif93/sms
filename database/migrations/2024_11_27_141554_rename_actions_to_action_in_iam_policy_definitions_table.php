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
            $table->renameColumn('actions', 'action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('iam_policy_definitions', function (Blueprint $table) {
            $table->renameColumn('action', 'actions');
        });
    }
};
