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
        Schema::create('webhook_services', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();  // e.g., 'channels'
            $table->string('display_name');    // e.g., 'Channels'
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Seed default services
        $services = [
            [
                'name' => 'channels',
                'display_name' => 'Channels',
                'description' => 'Channel-related webhooks',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'payments',
                'display_name' => 'Payments',
                'description' => 'Payment-related webhooks',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
   
        ];

        DB::table('webhook_services')->insert($services);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_services');
    }
};