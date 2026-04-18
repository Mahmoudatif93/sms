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
        // Run the seeder to populate notification templates
        \Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\NotificationTemplateSeeder'
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the seeded templates
        \App\Models\NotificationTemplate::whereIn('id', [
            'login_otp',
            'welcome_user',
            'admin_alert',
            'statistics_notification',
            'registration_otp',
            'new_user_admin_notification'
        ])->delete();
    }
};
