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
        Schema::create('ticket_form_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ticket_form_id');
            $table->string('label');
            $table->string('type'); // text, email, textarea, select, checkbox, radio, etc.
            $table->string('placeholder')->nullable();
            $table->text('options')->nullable(); // JSON for select, checkbox, radio options
            $table->boolean('is_required')->default(false);
            $table->integer('order')->default(0);
            $table->text('validation_rules')->nullable(); // JSON for additional validation rules
            $table->text('help_text')->nullable();
            $table->timestamps();
            
            $table->foreign('ticket_form_id')->references('id')->on('ticket_forms')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_form_fields');
    }
};
