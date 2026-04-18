<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    use SafeMigration;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->safeCreateTable('template_message_header_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('template_message_id');
            $table->string('type'); // e.g., image, video, document, text
            $table->timestamps();
        });

        // Add foreign key safely
        $this->safeAddForeignKey(
            'template_message_header_components',
            'template_message_id',
            'whatsapp_template_messages'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->safeDropForeignKey('template_message_header_components', 'template_message_header_components_template_message_id_fk');
        $this->safeDropTable('template_message_header_components');
    }
};
