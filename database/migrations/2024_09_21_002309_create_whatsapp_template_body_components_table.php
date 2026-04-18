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
        Schema::create('whatsapp_template_body_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('template_id');
            $table->text('text')->comment('Body text for the template');
            $table->timestamp('created_at')->nullable()->comment('Creation timestamp');
            $table->timestamp('updated_at')->nullable()->comment('Last update timestamp');

            // Foreign key constraint to the template
            $table->foreign('template_id')
                ->references('id')
                ->on('whatsapp_message_templates')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_template_body_components', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
        });
        Schema::dropIfExists('whatsapp_template_body_components');
    }
};
