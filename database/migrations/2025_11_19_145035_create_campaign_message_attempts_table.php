<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('campaign_message_attempts', function (Blueprint $table) {

            $table->id(); // normal primary key

            // FK to message log
            $table->unsignedBigInteger('message_log_id');

            // job attempt information
            $table->string('job_id')->nullable();
            $table->string('status')->default('dispatched');

            $table->string('exception_type')->nullable();
            $table->string('exception_message')->nullable();
            $table->longText('stack_trace')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();
        });

        $this->safeAddForeignKey(
            table: 'campaign_message_attempts',
            column: 'message_log_id',
            foreignTable: 'campaign_message_logs',
            foreignColumn: 'id',
            constraintName: 'campaign_message_attempts_msg_log_fk',
            onDelete: 'cascade'
        );
    }

    public function down(): void
    {
        $this->safeDropTable('campaign_message_attempts');
    }
};
