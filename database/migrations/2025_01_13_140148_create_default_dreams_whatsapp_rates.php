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
        Schema::create('default_dreams_whatsapp_rates', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->string('name')->nullable();
            $table->integer('country_id');
            $table->foreign('country_id')
                ->references('id')
                ->on('country') // References the country table
                ->onDelete('cascade');

            $table->foreignId('base_whatsapp_rate_id')
                ->constrained('whatsapp_rates') // References the WhatsappRate model
                ->onDelete('cascade');

            // Custom rates
            $table->float('custom_marketing_rate')->nullable();
            $table->float('custom_utility_rate')->nullable();
            $table->float('custom_authentication_rate')->nullable();
            $table->float('custom_authentication_international_rate')->nullable();
            $table->float('custom_service_rate')->nullable();

            // Rate metadata
            $table->timestamp('effective_date')->nullable(); // When the rate becomes active
            $table->timestamp('expiry_date')->nullable(); // When the rate expires
            $table->string('frequency')->nullable(); // Billing frequency (daily, weekly, etc.)
            $table->string('status')->default('inactive'); // Whether the rate is active or inactive

            $table->timestamps(); // Created at / Updated at timestamps
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_whatsapp_rates', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropForeign(['base_whatsapp_rate_id']);
        });
        Schema::dropIfExists('default_dreams_whatsapp_rates');
    }
};
