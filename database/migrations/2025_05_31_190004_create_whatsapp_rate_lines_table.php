<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Traits\SafeMigration;

return new class extends Migration
{
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('whatsapp_rate_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('world_country_id');
            $table->string('category'); // marketing, utility, etc.
            $table->decimal('price', 10, 4)->nullable();
            $table->string('currency', 10)->default('USD');
            $table->string('pricing_model', 10)->default('CBP'); // CBP or PMP
            $table->unsignedBigInteger('effective_date');
            $table->unsignedBigInteger('expiry_date')->nullable();
            $table->timestamps();
        });

        $this->safeAddForeignKey(
            'whatsapp_rate_lines',
            'world_country_id',
            'world_countries',
            'id',
            'whatsapp_rate_lines_world_country_id_fk'
        );
    }

    public function down(): void
    {
        $this->safeDropForeignKey('whatsapp_rate_lines', 'whatsapp_rate_lines_world_country_id_fk');
        $this->safeDropTable('whatsapp_rate_lines');
    }
};
