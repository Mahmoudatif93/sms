<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_auth_template_footer_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('whatsapp_message_templates')->cascadeOnDelete();
            $table->integer('code_expiration_minutes')->nullable(); // Specific to authentication footer
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_auth_template_footer_components', function (Blueprint $table) {
            // Drop the foreign key constraint before dropping the table
            $table->dropForeign(['template_id']);
        });

        Schema::dropIfExists('whatsapp_auth_template_footer_components');
    }
};
