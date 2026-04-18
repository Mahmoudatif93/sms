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
        $this->safeCreateTable('inbox_agent_availabilities', function (Blueprint $table) {
            $table->id();
            $table->integer('inbox_agent_id'); // Foreign key
            $table->string('timezone')->default('UTC')->nullable();
            $table->string('availability')->default('away')->nullable();
            $table->timestamps();
            $table->softDeletes(); // Enable soft deletes
        });

        // Use safeAddForeignKey instead of direct foreign key declaration
        $this->safeAddForeignKey(
            'inbox_agent_availabilities',
            'inbox_agent_id',
            'user'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->safeDropForeignKey('inbox_agent_availabilities', 'inbox_agent_availabilities_inbox_agent_id_fk');
        Schema::dropIfExists('inbox_agent_availabilities');
    }
};
