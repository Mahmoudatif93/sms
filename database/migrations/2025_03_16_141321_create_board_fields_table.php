<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('board_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('board_tab_id');
            $table->string('name');
            $table->string('type');
            $table->json('options')->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        $this->safeAddForeignKey('board_fields', 'board_tab_id', 'board_tabs', onDelete: 'cascade');
    }

    public function down(): void
    {
        $this->safeDropTable('board_fields');
    }
};
