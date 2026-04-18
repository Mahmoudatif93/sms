<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Traits\SafeMigration;

return new class extends Migration {
    use SafeMigration;

    public function up(): void {
        $this->safeCreateTable('deal_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('deal_id');
            $table->string('file_path');
            $table->timestamps();
        });

        $this->safeAddForeignKey(
            'deal_files',
            'deal_id',
            'deals',
            onDelete: 'cascade'
        );
    }

    public function down(): void {
        $this->safeDropTable('deal_files');
    }
};
