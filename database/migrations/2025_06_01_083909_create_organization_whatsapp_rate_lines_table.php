<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('organization_whatsapp_rate_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_rate_line_id')->constrained()->cascadeOnDelete();
            $table->float('custom_price')->nullable();
            $table->string('currency')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->safeDropForeignKey('organization_whatsapp_rate_lines', 'organization_whatsapp_rate_lines_organization_id_foreign');
        $this->safeDropForeignKey('organization_whatsapp_rate_lines', 'organization_whatsapp_rate_lines_whatsapp_rate_line_id_foreign');
        $this->safeDropTable('organization_whatsapp_rate_lines');
    }
};
