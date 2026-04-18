<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('board_tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('board_id'); // Foreign key for the board
            $table->string('name');
            $table->string('color')->default('#000000'); // Default black color
            $table->timestamps();
        });

        $this->safeAddForeignKey('board_tags', 'board_id', 'boards', onDelete: 'cascade');
    }

    public function down(): void
    {
        $this->safeDropTable('board_tags');
    }
};
