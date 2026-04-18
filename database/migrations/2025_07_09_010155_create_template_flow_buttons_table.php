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
        $this->safeCreateTable('template_flow_buttons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('button_component_id');
            $table->string('text');
            $table->string('flow_id')->nullable();
            $table->longText('flow_json')->nullable();
            $table->string('flow_action')->default('navigate');
            $table->string('navigate_screen')->nullable();
            $table->timestamps();
        });

        $this->safeAddForeignKey(
            'template_flow_buttons',
            'button_component_id',
            'whatsapp_template_button_components'
        );
    }

    public function down(): void
    {
        $this->safeDropForeignKey('template_flow_buttons', 'template_flow_buttons_button_component_id_fk');
        $this->safeDropTable('template_flow_buttons');
    }
};
