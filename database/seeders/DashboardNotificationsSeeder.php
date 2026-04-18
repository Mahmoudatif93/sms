<?php

namespace Database\Seeders;

use App\Models\DashboardNotification;
use App\Models\RequiredAction;
use Illuminate\Database\Seeder;

class DashboardNotificationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now()->timestamp;

        $requiredActions = [
            8 => '9db13013-1d65-4593-b02c-5c0ef5a05978',
            9 => '9dc15436-ccbc-4480-9481-d70c30ac9322',
            10 => '9de94ba5-d4f7-4a88-98ec-412789462bf0',
            11 => '9deb330d-f805-48cc-bb46-e4a618bb81ac',
            12 => '9deb3b75-60e7-4650-a0a4-52a7fd79dc76',
            13 => '9deb48b7-9cf4-4c61-a758-1a867b8aa5c6',
            14 => '9ded1284-72b1-40b6-8c96-10c51d47f926',
        ];

        foreach ($requiredActions as $requiredActionId => $channelId) {
            DashboardNotification::create([
                'title' => 'WhatsApp Channel Required Action Needed',
                'message' => 'One of your WhatsApp channels needs to be verified to stay active.',
                'link' => null,
                'icon' => 'whatsapp',
                'category' => 'required-actions',
                'workspace_id' => 'd64ed3a8-ceb0-4ed9-bee2-7df93ad47db5',
                'organization_id' => '6c6e56d4-7264-43c4-a819-7ca6089fe5bf',
                'notifiable_type' => RequiredAction::class,
                'notifiable_id' => $requiredActionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
