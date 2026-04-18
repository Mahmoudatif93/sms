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
        Schema::create('whatsapp_auth_template_body_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('whatsapp_message_templates')->cascadeOnDelete();
            $table->boolean('add_security_recommendation')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_auth_template_body_components', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['template_id']);
        });

        Schema::dropIfExists('whatsapp_auth_template_body_components');
    }
};
