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
        Schema::create('template_header_text_examples', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('header_text_component_id');
            $table->string('header_text')->comment('Example value for the TEXT header variable');
            $table->timestamp('created_at')->nullable()->comment('Creation timestamp');
            $table->timestamp('updated_at')->nullable()->comment('Last update timestamp');


            // Foreign key constraint to the TEXT header component
            $table->foreign('header_text_component_id')
                ->references('id')
                ->on('template_header_text_components')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_header_text_examples', function (Blueprint $table) {
            $table->dropForeign(['header_text_component_id']);
        });
        Schema::dropIfExists('template_header_text_examples');
    }
};
