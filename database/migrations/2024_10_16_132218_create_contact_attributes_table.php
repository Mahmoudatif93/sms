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
        Schema::create('contact_attributes', function (Blueprint $table) {
            $table->id()->primary();
            $table->uuid('contact_id'); // Reference to the contact
            $table->uuid('attribute_definition_id'); // Reference to the attribute definition
            $table->text('value'); // The value of the attribute (could be a string, phone, email, etc.)
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('contact_id')
                ->references('id')
                ->on('contacts')
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
        Schema::table('contact_attributes', function (Blueprint $table) {
            // Drop the foreign key constraints
            $table->dropForeign(['contact_id']);
            $table->dropForeign(['attribute_definition_id']);
        });
        Schema::dropIfExists('contact_attributes');
    }
};
