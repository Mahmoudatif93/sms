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
        Schema::create('whatsapp_rates', function (Blueprint $table) {
            $table->id();
            $table->integer('country_id');
            $table->foreign('country_id')
                ->references('id')
                ->on('country')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->string('currency', 10)->default('USD');
            $table->decimal('marketing', 10, 4)->nullable();
            $table->decimal('utility', 10, 4)->nullable();
            $table->decimal('authentication', 10, 4)->nullable();
            $table->decimal('authentication_international', 10, 4)->nullable();
            $table->decimal('service', 10, 4)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_rates');
    }
};
