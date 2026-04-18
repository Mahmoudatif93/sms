<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('meta_webhook_logs', function (Blueprint $table) {
            $table->id();

            // For large payloads, use longText. If you're on MySQL 5.7+ and want JSON validation, use ->json()
            $table->longText('payload');

            $table->boolean('processed')->default(false);
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->safeDropTable('whatsapp_webhook_logs');
    }
};
