<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use \App\Traits\SafeMigration;
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Fix existing NULL values before modifying the schema
        DB::statement("UPDATE user SET credit_limit = 0 WHERE credit_limit IS NULL");
        DB::statement("UPDATE user SET notification_has_sent = 0 WHERE notification_has_sent IS NULL");

        // 2. Safely change or create columns
        $this->safeChangeOrCreateColumn(
            'user',
            'credit_limit',
            fn(Blueprint $table) => $table->double('credit_limit')->nullable()->default(0)->change(),
            fn(Blueprint $table) => $table->double('credit_limit')->nullable()->default(0)
        );

        $this->safeChangeOrCreateColumn(
            'user',
            'notification_has_sent',
            fn(Blueprint $table) => $table->tinyInteger('notification_has_sent')->nullable()->default(0)->change(),
            fn(Blueprint $table) => $table->tinyInteger('notification_has_sent')->nullable()->default(0)
        );

        $this->safeChangeOrCreateColumn(
            'user',
            'granted_group_ids',
            fn(Blueprint $table) => $table->string('granted_group_ids')->nullable()->change(),
            fn(Blueprint $table) => $table->string('granted_group_ids')->nullable()
        );

        $this->safeChangeOrCreateColumn(
            'user',
            'granted_sender_ids',
            fn(Blueprint $table) => $table->string('granted_sender_ids')->nullable()->change(),
            fn(Blueprint $table) => $table->string('granted_sender_ids')->nullable()
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user', function (Blueprint $table) {
            //
        });
    }
};
