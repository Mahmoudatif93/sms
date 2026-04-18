<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Traits\SafeMigration;

return new class extends Migration {
    use SafeMigration;

    public function up(): void {
        $this->safeCreateTable('deals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('status')->nullable();
            $table->dateTime('due_date');
            $table->string('deal_type')->nullable(); // Nullable deal type
            $table->json('custom_fields')->nullable(); // Custom fields
            $table->uuid('workspace_id')->nullable();
            $table->uuid('pipeline_id');
            $table->decimal('amount', 15, 2)->nullable();
            $table->timestamps();
        });

        $this->safeAddForeignKey(
            'deals',
            'pipeline_id',
            'pipelines',
            onDelete: 'cascade'
        );
    }

    public function down(): void {
        $this->safeDropTable('deals');
    }
};
