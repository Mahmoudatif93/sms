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
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('name');          // e.g., 'sms.inbound'
            $table->string('display_name');   // e.g., 'SMS Inbound'
            $table->foreignId('webhook_service_id')->constrained()->onDelete('cascade');
            $table->text('description')->nullable();
            $table->json('payload_schema')->nullable();  // Expected JSON schema for the event
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['name', 'webhook_service_id']);
            $table->index('webhook_service_id');
        });
            $events = [
                [
                    'name' => 'sms.inbound',
                    'display_name' => 'SMS Inbound',
                    'webhook_service_id' => 1,
                    'description' => 'Triggered when an inbound SMS is received',
                    'is_active' => true
                ],
                [
                    'name' => 'sms.outbound',
                    'display_name' => 'SMS Outbound',
                    'webhook_service_id' => 1,
                    'description' => 'Triggered when an SMS is successfully delivered',
                    'is_active' => true
                ],
                [
                    'name' => 'sms.interactions',
                    'display_name' => 'SMS Interactions',
                    'webhook_service_id' => 1,
                    'description' => 'Triggered when SMS interactions',
                    'is_active' => false
                ],
                // Add more default events as needed
            ];

            DB::table('webhook_events')->insert($events);
     
        }
        public function down(): void
        {
            Schema::dropIfExists('webhook_events');
        }
    /**
     * Reverse the migrations.
     */
   
};
