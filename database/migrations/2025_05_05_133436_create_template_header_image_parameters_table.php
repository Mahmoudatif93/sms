<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Traits\SafeMigration;

return new class extends Migration
{
    use SafeMigration;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->safeCreateTable('template_header_image_parameters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tmpl_msg_hdr_component_id');
            $table->string('link'); // Meta-provided image handle (e.g., "4:...")
            $table->timestamps();
        });

        $this->safeAddForeignKey(
            'template_header_image_parameters',
            'tmpl_msg_hdr_component_id',
            'template_message_header_components',
            'id',
            'image_param_header_component_fk' // custom constraint name
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->safeDropForeignKey(
            'template_header_image_parameters',
            'image_param_header_component_fk'
        );

        $this->safeDropTable('template_header_image_parameters');
    }
};
