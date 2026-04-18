<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('task_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('task_id');
            $table->integer('user_id')->nullable();
            $table->string('action'); // e.g., "Task created", "Status changed"
            $table->string('file_path')->nullable(); // Store file path
            $table->timestamps();
        });

        $this->safeAddForeignKey('task_histories', 'task_id', 'tasks', onDelete: 'cascade');
        $this->safeAddForeignKey('task_histories', 'user_id', 'user', onDelete: 'set null');
    }

    public function down(): void
    {
        $this->safeDropTable('task_histories');
    }
};
