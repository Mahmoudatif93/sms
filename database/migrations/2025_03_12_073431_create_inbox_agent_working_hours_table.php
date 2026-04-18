<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    use SafeMigration;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->safeCreateTable('inbox_agent_working_hours', function (Blueprint $table) {
            $table->id();
            $table->integer('inbox_agent_id'); // Foreign key
            $table->string('day'); // e.g., "Monday", "Tuesday", etc.
            $table->time('start_time')->nullable(); // Start time for the working period
            $table->time('end_time')->nullable(); // End time for the working period
            $table->timestamps();
            $table->softDeletes(); // Enable soft deletes
        });

        // Safely add foreign key constraint
        $this->safeAddForeignKey(
            'inbox_agent_working_hours',
            'inbox_agent_id',
            'user');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->safeDropForeignKey('inbox_agent_working_hours', 'inbox_agent_working_hours_inbox_agent_id_fk');
        $this->safeDropTable('inbox_agent_working_hours');
    }
};
