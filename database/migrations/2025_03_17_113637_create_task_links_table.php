<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('task_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('task_id')->index();
            $table->uuid('linked_id')->index();
            $table->enum('linked_type', ['tasks', 'deals', 'contacts']);
            $table->timestamps();
        });

        $this->safeAddForeignKey('task_links', 'task_id', 'tasks', onDelete: 'cascade');
    }

    public function down(): void
    {
        $this->safeDropTable('task_links');
    }
};
