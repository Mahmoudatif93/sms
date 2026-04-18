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
        Schema::create('sms_configurations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Foreign key references
            $table->uuid('connector_id');
            $table->unsignedBigInteger('sender_id');
            // Additional fields
            $table->string('status')->default('active');

            // Timestamps
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('connector_id')->references('id')->on('connectors')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_configurations');
    }
};
