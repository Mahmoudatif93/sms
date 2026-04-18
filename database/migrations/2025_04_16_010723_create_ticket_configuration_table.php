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
        Schema::create('ticket_configuration', function (Blueprint $table) {
            $table->id();
            $table->uuid('connector_id');
            $table->string('status')->default('active');
            $table->uuid('ticket_form_id')->nullable();
            $table->foreign('ticket_form_id')->references('id')->on('ticket_forms')->nullOnDelete();
            $table->foreign('connector_id')->references('id')->on('connectors')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_configuration');
    }
};
