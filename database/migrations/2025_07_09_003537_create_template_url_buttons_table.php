<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('template_url_buttons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('button_component_id');
            $table->string('text', 25)->comment('Text displayed on the button');
            $table->string('url', 2000)->comment('URL that loads when the button is tapped');
            $table->string('example')->nullable()->comment('Sample variable value for dynamic URL (e.g., {{1}})');
            $table->timestamps();
        });

        $this->safeAddForeignKey(
            'template_url_buttons',
            'button_component_id',
            'whatsapp_template_button_components',
            'id',
            'template_url_buttons_button_component_id_fk'
        );
    }

    public function down(): void
    {
        $this->safeDropForeignKey('template_url_buttons', 'template_url_buttons_button_component_id_fk');
        $this->safeDropTable('template_url_buttons');
    }
};
