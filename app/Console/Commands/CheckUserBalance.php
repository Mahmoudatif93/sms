<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Setting;
use App\Services\SendLoginNotificationService;

class CheckUserBalance extends Command
{
    protected $sendNotification;

    public function __construct(SendLoginNotificationService $sendNotification)
    {
        parent::__construct(); // Ensure parent constructor is called
        $this->sendNotification = $sendNotification;
    }

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:user-balance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify Admin when users have low balance';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Fetch settings
        $systemSmsSender = Setting::get_by_name('system_sms_sender');
        $siteName = Setting::get_by_name('site_name');

        // Fetch users with warning balances
        $users = User::getWarningBalance();

        if ($users->isEmpty()) {
            $this->info("No users to Avaliables.");
            return 0; // Exit if no users 
        }

        foreach ($users as $user) {
            $message = $user->username . "انخفاض في مستخدم "; 
            $this->sendNotification->sendSmsNotification(
                $systemSmsSender,
                '966595555672', // Admin's phone number
                $message,
                'admin'
            );
            // Log each notification in the console
            $this->info("Notification sent for admin");
        }

        return 0;
    }
}
