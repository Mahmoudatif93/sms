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
        Schema::create('segment_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('segment_id'); // Reference to the segment
            $table->uuid('attribute_definition_id'); // Reference to attribute definition
            $table->string('operator'); // Rule operator (e.g., equals, not equals)
            $table->string('value')->nullable(); // Rule value (if applicable)
            $table->timestamps();

            $table->foreign('segment_id')
                ->references('id')
                ->on('segments')
                ->onDelete('cascade');

            $table->foreign('attribute_definition_id')
                ->references('id')
                ->on('attribute_definitions')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('segment_rules', function (Blueprint $table) {
            $table->dropForeign(['segment_id']);
            $table->dropForeign(['attribute_definition_id']);
        });

        Schema::dropIfExists('segment_rules');
    }
};
