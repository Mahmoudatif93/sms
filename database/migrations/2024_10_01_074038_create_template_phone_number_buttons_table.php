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
        Schema::create('template_phone_number_buttons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('button_component_id');
            $table->string('text', 25)->comment('Text displayed on the button');
            $table->string('phone_number', 20)->comment('Phone number to be called when the button is tapped');
            $table->timestamps();

            // Foreign key constraint to the button component
            $table->foreign('button_component_id')
                ->references('id')
                ->on('whatsapp_template_button_components')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_phone_number_buttons', function (Blueprint $table) {
            $table->dropForeign(['button_component_id']);
        });
        Schema::dropIfExists('template_phone_number_buttons');
    }
};
