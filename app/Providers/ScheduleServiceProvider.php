<?php

namespace App\Providers;

use App\Jobs\ProcessInboxAgentBillingsJob;
use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

class ScheduleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->app->afterResolving(Schedule::class, function (Schedule $schedule) {
            // Send notifications daily at 9 AM
            $schedule->command('channels:check-expiry')
            ->dailyAt('09:00')
                ->name('notify-channel-expiry')
                ->onFailure(function () {
                    Log::error('Channel expiry notification failed to run');
                });

            // Check and disable expired channels every hour
            $schedule->command('channels:disable-expired')
            ->dailyAt('09:00')
                ->name('disable-expired-channels')
                ->onFailure(function () {
                    Log::error('Disable expired channels failed to run');
                });

            // Process inbox agent subscription renewals daily
            $schedule->job(new ProcessInboxAgentBillingsJob())
                ->daily()
                ->name('process-inbox-agent-billings')
                ->onFailure(function () {
                    Log::error('Process inbox agent billings failed to run');
                });
        });
    }
}
