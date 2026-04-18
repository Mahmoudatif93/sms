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
        Schema::table('chatbot_settings', function (Blueprint $table) {
            // Working hours per day (JSON with start/end for each day)
            // Example: {"sunday": {"start": "10:00", "end": "22:00"}, "friday": {"start": "14:00", "end": "23:00"}, ...}
            $table->json('working_hours')->nullable()->after('handoff_threshold');

            // Timezone for working hours calculation
            $table->string('timezone', 50)->default('Asia/Riyadh')->after('working_hours');

            // Message to send when outside working hours (Arabic)
            $table->text('outside_hours_message_ar')->nullable()->after('timezone');

            // Message to send when outside working hours (English)
            $table->text('outside_hours_message_en')->nullable()->after('outside_hours_message_ar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chatbot_settings', function (Blueprint $table) {
            $table->dropColumn([
                'working_hours',
                'timezone',
                'outside_hours_message_ar',
                'outside_hours_message_en',
            ]);
        });
    }
};
