<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('deal_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('deal_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action'); // e.g., "Deal created", "Status changed"
            $table->string('file_path')->nullable(); // Store file path
            $table->timestamps();
        });

        $this->safeAddForeignKey(
            'deal_histories',
            'deal_id',
            'deals',
            onDelete: 'cascade'
        );
    }

    public function down(): void
    {
        $this->safeDropTable('deal_histories');
    }
};
