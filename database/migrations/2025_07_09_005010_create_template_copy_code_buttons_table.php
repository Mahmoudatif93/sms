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
        $this->safeCreateTable('template_copy_code_buttons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('button_component_id');
            $table->string('example', 15)->comment('Example value for the COPY_CODE button');
            $table->timestamps();
        });

        $this->safeAddForeignKey(
            'template_copy_code_buttons',
            'button_component_id',
            'whatsapp_template_button_components',
            'id',
            'template_copy_code_buttons_button_component_id_fk'
        );
    }

    public function down(): void
    {
        $this->safeDropForeignKey('template_copy_code_buttons', 'template_copy_code_buttons_button_component_id_fk');
        $this->safeDropTable('template_copy_code_buttons');
    }
};
