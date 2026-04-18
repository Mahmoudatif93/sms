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
        Schema::create('whatsapp_auth_template_button_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('whatsapp_message_templates')->cascadeOnDelete();
            $table->string('otp_type'); // OTP type (copy_code, one_tap, zero_tap)
            $table->string('text')->nullable(); // Optional text for the button
            $table->string('autofill_text')->nullable(); // Optional autofill text (for one_tap or zero_tap)
            $table->boolean('zero_tap_terms_accepted')->nullable(); // Optional for zero_tap
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_auth_template_button_components', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['template_id']);
        });
        Schema::dropIfExists('whatsapp_auth_template_button_components');
    }
};
