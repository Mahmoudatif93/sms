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
        Schema::create('template_body_text_examples', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('body_text_component_id');
            $table->string('body_text')->comment('Example value for the body text variable');
            $table->timestamp('created_at')->nullable()->comment('Creation timestamp');
            $table->timestamp('updated_at')->nullable()->comment('Last update timestamp');

            // Foreign key constraint to the body text component
            $table->foreign('body_text_component_id')
                ->references('id')
                ->on('whatsapp_template_body_components')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_body_text_examples', function (Blueprint $table) {
            $table->dropForeign(['body_text_component_id']);
        });
        Schema::dropIfExists('template_body_text_examples');
    }
};
