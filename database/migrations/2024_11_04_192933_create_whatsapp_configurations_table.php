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
        Schema::create('whatsapp_configurations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Foreign key references
            $table->uuid('connector_id');
            $table->unsignedBigInteger('business_manager_account_id');
            $table->unsignedBigInteger('whatsapp_business_account_id');

            // Additional fields
            $table->string('status')->default('active');
            $table->boolean('is_sandbox')->default(false);

            // Timestamps
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('connector_id')->references('id')->on('connectors')->onDelete('cascade');
            $table->foreign('business_manager_account_id')->references('id')->on('business_manager_accounts')->onDelete('cascade');
            $table->foreign('whatsapp_business_account_id')->references('id')->on('whatsapp_business_accounts')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_configurations', function (Blueprint $table) {
            // Drop foreign key constraints first
            $table->dropForeign(['connector_id']);
            $table->dropForeign(['business_manager_account_id']);
            $table->dropForeign(['whatsapp_business_account_id']);
        });
        Schema::dropIfExists('whatsapp_configurations');
    }
};
