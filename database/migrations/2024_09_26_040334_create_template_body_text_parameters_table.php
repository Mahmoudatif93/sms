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
        Schema::create('template_body_text_parameters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('template_message_body_component_id');
            $table->text('text');
            $table->timestamps();

            $table->foreign('template_message_body_component_id', 'text_param_body_component_fk')
                ->references('id')
                ->on('template_message_body_components')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_body_text_parameters', function (Blueprint $table) {
            $table->dropForeign(['template_message_body_component_id']);
        });
        Schema::dropIfExists('template_body_text_parameters');
    }
};
