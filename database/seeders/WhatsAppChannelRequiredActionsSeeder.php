<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Channel;
use Illuminate\Support\Carbon;

class WhatsAppChannelRequiredActionsSeeder extends Seeder
{
    public function run(): void
    {
        $organizationId = '6c6e56d4-7264-43c4-a819-7ca6089fe5bf';
        $workspaceId = 'd64ed3a8-ceb0-4ed9-bee2-7df93ad47db5';

        $channels = Channel::where('platform', Channel::WHATSAPP_PLATFORM)->get();

        if ($channels->isEmpty()) {
            $this->command->warn('No WhatsApp channels found. Seeder skipped.');
            return;
        }

        foreach ($channels as $channel) {
            $exists = $channel->requiredActions()
                ->where('action_type', 'verify_phone_number')
                ->where('organization_id', $organizationId)
                ->where('workspace_id', $workspaceId)
                ->exists();

            if ($exists) {
                $this->command->line("⚠️  Skipped — Action already exists for channel {$channel->id}.");
                continue;
            }

            $channel->requiredActions()->create([
                'action_type'     => 'verify_phone_number',
                'metadata'        => [
                    'title'   => 'Verify WhatsApp Phone Number',
                    'message' => 'Please verify your number at <a href="https://business.facebook.com/wa/manage/phone-numbers">WhatsApp Phone Manager</a>',
                ],
                'due_at'          => Carbon::now()->addDays(5),
                'workspace_id'    => $workspaceId,
                'organization_id' => $organizationId,
            ]);

            $this->command->info("✅ Action seeded for channel {$channel->id}.");
        }

        $this->command->comment('📦 WhatsAppChannelRequiredActionsSeeder complete.');
    }
}
