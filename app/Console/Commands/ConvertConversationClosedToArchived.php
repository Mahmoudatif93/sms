<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Conversation;

class ConvertConversationClosedToArchived extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:convert-conversation-closed-to-archived {--workspace=* : Filter by workspace IDs}';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $query = Conversation::where('status', Conversation::STATUS_CLOSED);
        // Apply workspace filter if provided
        if ($workspaceIds = $this->option('workspace')) {
            $query->whereIn('workspace_id', $workspaceIds);
        }
        // Get count first for display
        $count = $query->count();

        if ($count === 0) {
            $this->info('No closed conversations found to archive.');
            return Command::SUCCESS;
        }
  
        $query->update(['status' => Conversation::STATUS_ARCHIVED]);

        $this->info("{$count} conversations have been converted from closed to archived status.");

        return Command::SUCCESS;
    }
}
