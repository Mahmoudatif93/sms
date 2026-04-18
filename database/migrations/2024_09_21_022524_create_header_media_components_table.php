<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('template_header_media_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('header_component_id');
            $table->text('header_handle')->comment('Media Example Header Handle');

            $table->timestamp('created_at')->nullable()->comment('Creation timestamp');
            $table->timestamp('updated_at')->nullable()->comment('Last update timestamp');

            // Foreign key constraint to the template
            $table->foreign('header_component_id')
                ->references('id')
                ->on('whatsapp_template_header_components')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_header_media_components', function (Blueprint $table) {
            $table->dropForeign(['header_component_id']);
        });
        Schema::dropIfExists('template_header_media_components');
    }
};
