<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('deal_reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('deal_id');
            $table->dateTime('reminder_date');
            $table->string('note')->nullable();
            $table->timestamps();
        });

        $this->safeAddForeignKey(
            'deal_reminders',
            'deal_id',
            'deals',
            onDelete: 'cascade'
        );
    }

    public function down(): void
    {
        $this->safeDropTable('deal_reminders');
    }
};
