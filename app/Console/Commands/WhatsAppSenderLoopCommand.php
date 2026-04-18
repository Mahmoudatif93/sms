<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\WhatsAppCompanySenderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Sender Loop Command
 * 
 * Runs 24/7 processing messages for all companies in parallel
 * Each company respects its own TPS limit
 */
class WhatsAppSenderLoopCommand extends Command
{
    protected $signature = 'whatsapp:sender-loop 
                            {--refresh-interval=60 : Seconds between organization list refresh}
                            {--loop-delay=10000 : Microseconds delay between loop iterations (default: 10ms)}';

    protected $description = 'Process WhatsApp messages for all companies respecting TPS (runs 24/7)';

    protected WhatsAppCompanySenderService $senderService;
    protected array $organizations = [];
    protected int $lastRefresh = 0;

    public function __construct()
    {
        parent::__construct();
        $this->senderService = app(WhatsAppCompanySenderService::class);
    }

    public function handle()
    {
        $this->info("🚀 WhatsApp Sender Loop Started");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $refreshInterval = (int) $this->option('refresh-interval');
        $loopDelay = (int) $this->option('loop-delay');

        $this->info("⚙️  Configuration:");
        $this->info("   - Organization refresh interval: {$refreshInterval} seconds");
        $this->info("   - Loop delay: {$loopDelay} microseconds");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

        // Initial load
        $this->refreshOrganizations();

        $iteration = 0;
        $messagesSent = 0;
        $lastStatsTime = time();

        while (true) {
            try {
                $iteration++;

                // Refresh organization list periodically
                if (time() - $this->lastRefresh >= $refreshInterval) {
                    $this->refreshOrganizations();
                }

                // Process all organizations
                $sentInThisIteration = 0;
                foreach ($this->organizations as $organizationId) {
                    $sent = $this->senderService->sendForCompany($organizationId);
                    if ($sent) {
                        $sentInThisIteration++;
                        $messagesSent++;
                    }
                }

                // Display stats every 10 seconds
                if (time() - $lastStatsTime >= 10) {
                    $this->displayStats($messagesSent, $iteration);
                    $lastStatsTime = time();
                }

                // Small delay to reduce CPU usage
                usleep($loopDelay);

            } catch (\Throwable $e) {
                Log::error("WhatsAppSenderLoopCommand: Error in main loop", [
                    'error' => $e->getMessage(),
                    'stack' => $e->getTraceAsString()
                ]);

                $this->error("❌ Error: " . $e->getMessage());
                
                // Sleep a bit longer on error to avoid rapid error loops
                sleep(5);
            }
        }

        return 0;
    }

    /**
     * Refresh the list of organizations
     */
    protected function refreshOrganizations(): void
    {
        $this->organizations = Organization::pluck('id')->toArray();
        $this->lastRefresh = time();

        $count = count($this->organizations);
        $this->info("🔄 Organizations refreshed: {$count} organizations loaded");

        if ($count === 0) {
            $this->warn("⚠️  No organizations found!");
        }
    }

    /**
     * Display statistics
     */
    protected function displayStats(int $messagesSent, int $iteration): void
    {
        $pending = $this->senderService->getOrganizationsWithPendingMessages();
        $totalPending = array_sum($pending);

        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📊 Stats:");
        $this->info("   - Total messages sent: {$messagesSent}");
        $this->info("   - Total iterations: {$iteration}");
        $this->info("   - Pending messages: {$totalPending}");
        
        if (!empty($pending)) {
            $this->info("   - Per organization:");
            foreach ($pending as $orgId => $count) {
                $this->info("     • Org {$orgId}: {$count} messages");
            }
        }
        
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");
    }
}

