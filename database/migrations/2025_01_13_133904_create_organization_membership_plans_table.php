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
        Schema::create('organization_membership_plans', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->uuid('organization_id'); // Reference to the organization
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->unsignedBigInteger('service_id'); // Reference to the service
            $table->foreign('service_id')
                ->references('id')
                ->on('services')
                ->onDelete('cascade');

            $table->float('price')->nullable(); // Membership price
            $table->string('currency')->nullable();
            $table->string('frequency'); // Billing frequency (e.g., monthly, yearly)
            $table->string('status')->default('inactive'); // Membership status
            $table->date('start_date'); // Start date of the plan
            $table->date('end_date')->nullable(); // End date of the plan (nullable)
            $table->timestamps(); // Created and updated timestamps
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_membership_plans', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['service_id']);
        });
        Schema::dropIfExists('organization_membership_plans');
    }
};
