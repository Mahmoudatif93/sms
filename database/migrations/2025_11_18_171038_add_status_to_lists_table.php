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
        Schema::table('lists', function (Blueprint $table) {
            $table->enum('status', ['active', 'pending', 'failed'])->default('active')->after('description');
            $table->integer('total_contacts')->default(0)->after('status');
            $table->integer('processed_contacts')->default(0)->after('total_contacts');
            $table->text('error_message')->nullable()->after('processed_contacts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lists', function (Blueprint $table) {
            $table->dropColumn(['status', 'total_contacts', 'processed_contacts', 'error_message']);
        });
    }
};
