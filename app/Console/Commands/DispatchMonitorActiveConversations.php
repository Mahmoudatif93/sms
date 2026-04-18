<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\WhatsappMessage;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class DispatchMonitorActiveConversations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:monitor-active-conversations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move active conversations to waiting if the last inbound message is unanswered for too long.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $conversations = Conversation::where('status', Conversation::STATUS_ACTIVE)->get();

        if ($conversations->isEmpty()) {
            $this->info('No active conversations to monitor.');
            return CommandAlias::SUCCESS;
        }

        $count = 0;

        foreach ($conversations as $conversation) {

            $settings = $conversation->workspace?->organization?->getOrCreateInboxAgentSettings();
            $lastMessage = $conversation?->messages()?->latest()?->first();

            if (!$lastMessage) {
                continue;
            }

            $idleTime = now()->timestamp - $lastMessage->created_at;


            // Condition: last inbound message and exceeded an idle threshold
            if ($lastMessage->direction === WhatsappMessage::MESSAGE_DIRECTION_RECEIVED && $idleTime >= $settings->wait_time_idle) {
                $conversation->status = Conversation::STATUS_WAITING;
                $conversation->save();
                $count++;
            }
        }

        $this->info("{$count} conversations moved from active to waiting.");
        return CommandAlias::SUCCESS;
    }
}
