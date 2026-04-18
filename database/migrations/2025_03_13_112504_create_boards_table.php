<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('boards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('color')->nullable();
            $table->integer('assigned_to')->nullable();
            $table->timestamps();
        });

        $this->safeAddForeignKey('boards', 'assigned_to', 'user', onDelete: 'set null');
    }

    public function down(): void
    {
        $this->safeDropTable('boards');
    }
};
