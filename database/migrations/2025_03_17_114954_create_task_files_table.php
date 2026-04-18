<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('task_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('task_id');
            $table->string('file_path');
            $table->timestamps();
        });

        $this->safeAddForeignKey('task_files', 'task_id', 'tasks', onDelete: 'cascade');
    }

    public function down(): void
    {
        $this->safeDropTable('task_files');
    }
};
