<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('task_tags', function (Blueprint $table) {
            $table->uuid('task_id');
            $table->uuid('board_tag_id');
            $table->primary(['task_id', 'board_tag_id']);
        });

        $this->safeAddForeignKey('task_tags', 'task_id', 'tasks', onDelete: 'cascade');
        $this->safeAddForeignKey('task_tags', 'board_tag_id', 'board_tags', onDelete: 'cascade');
    }

    public function down(): void
    {
        $this->safeDropTable('task_tags');
    }
};
