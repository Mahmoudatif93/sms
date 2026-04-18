<?php

use App\Jobs\ProcessHostingPlansJob;
use App\Jobs\ProcessMembershipPlansJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use App\Models\Otp;
use Illuminate\Support\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

/*
Schedule::call(function () {
    Otp::where('expires_at', '<', Carbon::now())->delete();
    $this->info('Expired OTPs cleaned up.');
})->everyMinute();*/
Schedule::command('sms:send-sms-later')->everyMinute()->withoutOverlapping()->onOneServer();

Schedule::call(function () {
    ProcessMembershipPlansJob::dispatch();
})->name('process-membership-plans')
    ->dailyAt('00:00')->withoutOverlapping()->onOneServer();

Schedule::call(function () {
    ProcessHostingPlansJob::dispatch();
})
    ->name('process-hosting-plans') // Assign a unique name
    ->dailyAt('00:00') // Runs daily at midnight
    ->withoutOverlapping() // Ensures no overlapping
    ->onOneServer(); // Runs only once in a distributed environment

Schedule::command('app:convert-conversation-closed-to-archived')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('app:monitor-active-conversations')->everyMinute()->withoutOverlapping()->onOneServer();

Schedule::command('migrations:run')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

