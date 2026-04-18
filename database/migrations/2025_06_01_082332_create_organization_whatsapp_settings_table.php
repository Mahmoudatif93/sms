<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('organization_whatsapp_settings', function (Blueprint $table) {
            $table->id()->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->boolean('use_custom_rates')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Drop the foreign key constraint before dropping the table
        $this->safeDropForeignKey('organization_whatsapp_settings', 'organization_whatsapp_settings_organization_id_foreign');
        $this->safeDropTable('organization_whatsapp_settings');
    }
};
