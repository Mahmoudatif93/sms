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
        Schema::create('organization_whatsapp_extras', function (Blueprint $table) {
            $table->id();
            $table->uuid('organization_id'); // Foreign key to organizations table
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            // Quota columns
            $table->float('translation_quota')->default(0); // Translation Quota
            $table->float('chatbot_quota')->default(0); // ChatBot Quota
            $table->float('hosting_quota')->default(0); // Hosting Quota
            $table->float('inbox_agent_quota')->default(0); // Inbox Agent Quota

            // Free Tier
            $table->boolean('free_tier')->default(false); // Is Free Tier Enabled
            $table->float('free_tier_limit')->default(1000); // Free Tier limit
            // Additional metadata
            $table->string('frequency')->nullable(); // Frequency (e.g., monthly, yearly)
            $table->timestamp('effective_date')->nullable(); // When the quota becomes active
            $table->timestamp('expiry_date')->nullable(); // When the quota expires
            $table->boolean('is_active')->default(true); // Whether the quota is active

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_whatsapp_extras', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
        });
        Schema::dropIfExists('organization_whatsapp_extras');
    }
};
