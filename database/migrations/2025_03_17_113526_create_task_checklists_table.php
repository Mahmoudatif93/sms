<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('task_checklists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('task_id')->index();
            $table->string('name');
            $table->timestamps();
        });

        $this->safeAddForeignKey('task_checklists', 'task_id', 'tasks', onDelete: 'cascade');
    }

    public function down(): void
    {
        $this->safeDropTable('task_checklists');
    }
};
