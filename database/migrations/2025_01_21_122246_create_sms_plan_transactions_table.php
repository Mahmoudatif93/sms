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
        Schema::create('sms_plan_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('payment_id');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
            $table->integer('plan_id');
            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('cascade');
            $table->integer('points_allocated');
            $table->decimal('price_per_point', 15, 4);
            $table->string('currency');
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_plan_transactions');
    }
};
