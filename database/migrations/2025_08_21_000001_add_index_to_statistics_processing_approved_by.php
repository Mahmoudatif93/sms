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
        Schema::table('statistics_processing', function (Blueprint $table) {
            // Add index for approved_by to improve query performance for auto-approval checks
            $table->index('approved_by', 'idx_statistics_processing_approved_by');
            
            // Add composite index for status and approved_by for better performance
            $table->index(['status', 'approved_by'], 'idx_statistics_processing_status_approved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('statistics_processing', function (Blueprint $table) {
            $table->dropIndex('idx_statistics_processing_approved_by');
            $table->dropIndex('idx_statistics_processing_status_approved_by');
        });
    }
};
