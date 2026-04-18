<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('task_repeats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('task_id');
            $table->boolean('is_recurring')->default(false); // متكرر
            $table->string('repeat_frequency')->nullable(); // daily, weekly, monthly, yearly
            $table->integer('repeat_interval')->nullable(); // Repeat every X days/weeks/months
            $table->json('repeat_days')->nullable(); // If weekly, store days ["Monday", "Wednesday"]
            $table->date('repeat_until')->nullable(); // Optional: Date to stop repetition
            $table->timestamps();
        });

        $this->safeAddForeignKey('task_repeats', 'task_id', 'tasks', onDelete: 'cascade');
    }

    public function down(): void
    {
        $this->safeDropTable('task_repeats');
    }
};
