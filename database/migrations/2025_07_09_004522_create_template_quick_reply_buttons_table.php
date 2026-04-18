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
        $this->safeCreateTable('template_quick_reply_buttons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('button_component_id');
            $table->string('text', 25)->comment('Text displayed on the quick reply button');
            $table->timestamps();
        });

        $this->safeAddForeignKey(
            'template_quick_reply_buttons',
            'button_component_id',
            'whatsapp_template_button_components',
            'id',
            'template_quick_reply_buttons_button_component_id_fk'
        );
    }

    public function down(): void
    {
        $this->safeDropForeignKey('template_quick_reply_buttons', 'template_quick_reply_buttons_button_component_id_fk');
        $this->safeDropTable('template_quick_reply_buttons');
    }
};
