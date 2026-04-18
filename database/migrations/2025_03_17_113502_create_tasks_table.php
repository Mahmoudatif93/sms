<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->uuid('board_id')->index();
            $table->uuid('board_stage_id')->index();
            $table->enum('priority', ['High', 'Normal', 'Low'])->default('Normal');
            $table->datetime('start_date')->nullable();
            $table->datetime('due_date')->nullable();
            $table->uuid('parent_task_id')->nullable()->index();
            $table->json('custom_fields')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        $this->safeAddForeignKey('tasks', 'board_id', 'boards', onDelete: 'cascade');
        $this->safeAddForeignKey('tasks', 'board_stage_id', 'board_stages', onDelete: 'cascade');
        $this->safeAddForeignKey('tasks', 'parent_task_id', 'tasks', onDelete: 'set null');
    }

    public function down(): void
    {
        $this->safeDropTable('tasks');
    }
};
