<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RemoveSuspendUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'remove:suspend-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove suspended users after a specified suspension time.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $minutes = Setting::get_by_name('faild_login_time');
        $cutoffTime = Carbon::now()->subMinutes($minutes);
        $suspendedUsers = User::whereNotNull('suspended_at')
            ->where('suspended_at', '<=', $cutoffTime)
            ->get();
        // Check if we have users to update
        if ($suspendedUsers->isNotEmpty()) {
            $usersToUpdate = $suspendedUsers->pluck('id')->toArray();

            // Perform a batch update
            User::whereIn('id', $usersToUpdate)
                ->update([
                    'suspended_at' => null,
                    'faild_count_login' => 0,
                ]);

            $this->info("Suspension removed for " . count($usersToUpdate) . " user(s).");
        } else {
            $this->info("No users to unsuspend.");
        }

        return 0; // Return success code
    }
}
