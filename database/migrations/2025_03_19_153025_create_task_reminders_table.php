<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('task_reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('task_id');
            $table->dateTime('reminder_date');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        $this->safeAddForeignKey('task_reminders', 'task_id', 'tasks', onDelete: 'cascade');
    }

    public function down(): void
    {
        $this->safeDropTable('task_reminders');
    }
};
