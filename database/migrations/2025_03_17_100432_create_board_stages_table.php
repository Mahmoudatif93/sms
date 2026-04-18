<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('board_stages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('board_id');
            $table->string('name');
            $table->string('color')->default('#000000');
            $table->timestamps();
        });

        $this->safeAddForeignKey('board_stages', 'board_id', 'boards', onDelete: 'cascade');
    }

    public function down(): void
    {
        $this->safeDropTable('board_stages');
    }
};
