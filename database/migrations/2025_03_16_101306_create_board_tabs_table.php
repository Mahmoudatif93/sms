<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('board_tabs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('board_id');
            $table->string('name');
            $table->integer('position');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        $this->safeAddForeignKey('board_tabs', 'board_id', 'boards', onDelete: 'cascade');
    }

    public function down(): void
    {
        $this->safeDropTable('board_tabs');
    }
};
