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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');  // visa, bank_transfer, etc.
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->json('configuration')->nullable(); // Store payment gateway configs
            $table->timestamps();
        });
        DB::table('payment_methods')->insert([
            ['name' => 'visa', 'code' => 'visa', 'is_active' => true, 'configuration' => json_encode([])],
            ['name' => 'bank_transfer', 'code' => 'bank_transfer', 'is_active' => true, 'configuration' => json_encode([])],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
