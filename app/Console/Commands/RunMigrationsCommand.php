<?php

namespace App\Console\Commands;

use App\Http\Slack;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;
use Sentry;

class RunMigrationsCommand extends Command
{
    protected $signature = 'migrations:run';
    protected $description = 'Run all pending migrations with Sentry + Slack alerting';

    public function handle()
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();

            Log::info('Migrations executed successfully', ['output' => $output]);
            $this->info('Migrations executed successfully.');
        } catch (Throwable $e) {
            // ⚠️ Send to Sentry
            if (app()->bound('sentry')) {
                Sentry\captureException($e);
            }

            // Log locally
            Log::error('Migration failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // (Optional) also use your Slack logger
            Slack::Log("Migration failed ❌: {$e->getMessage()}", __FILE__, __LINE__, 'whatsapp');

            $this->error('Migration failed: ' . $e->getMessage());
        }
    }
}
