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
        Schema::create('statistics_processing', function (Blueprint $table) {
            $table->id();
            $table->string('processing_id')->unique(); // Unique identifier for the processing job
            $table->unsignedInteger('user_id');
            $table->char('workspace_id', 36)->nullable();
            $table->text('all_numbers'); // Original numbers input
            $table->string('sender_name', 255);
            $table->text('message');
            $table->enum('send_time_method', ['NOW', 'LATER']);
            $table->timestamp('send_time')->nullable();
            $table->enum('sms_type', ['NORMAL', 'VARIABLES', 'ADS', 'VOICE', 'CALENDAR']);
            $table->unsignedInteger('repeation_times')->nullable();
            $table->string('excel_file', 255)->nullable();
            $table->unsignedInteger('message_length');

            // Processing status and results
            $table->enum('status', ['PENDING', 'PROCESSING', 'COMPLETED', 'FAILED', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->unsignedInteger('total_numbers')->default(0);
            $table->unsignedInteger('processed_numbers')->default(0);
            $table->decimal('total_cost', 10, 4)->default(0);
            $table->longText('entries_json')->nullable(); // Country breakdown
            $table->longText('all_numbers_json')->nullable(); // Processed numbers with details
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('approved_by')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('processing_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statistics_processing');
    }
};
