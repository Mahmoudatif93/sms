<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('task_checklist_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('checklist_id')->index();
            $table->string('name');
            $table->boolean('is_completed')->default(false);
            $table->timestamps();
        });

        $this->safeAddForeignKey('task_checklist_items', 'checklist_id', 'task_checklists', onDelete: 'cascade');
    }

    public function down(): void
    {
        $this->safeDropTable('task_checklist_items');
    }
};
