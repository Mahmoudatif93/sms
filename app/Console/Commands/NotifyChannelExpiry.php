<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Notifications\ChannelExpiryNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyChannelExpiry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'channels:check-expiry {--days=5 : Days before expiry to send notification}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for channels nearing expiration and notify workspace users';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $daysBeforeExpiry = $this->option('days');
        $checkDate = Carbon::now()->addDays((int) $daysBeforeExpiry);
        try {
            $expiredChannels = Channel::where('platform', Channel::SMS_PLATFORM)
                ->where('status', Channel::STATUS_ACTIVE)
                ->with([
                    'connector.smsConfiguration.sender',
                    'connector.workspace.organization.owner',
                ])
                ->whereHas('connector.smsConfiguration.sender', function ($query) use ($checkDate) {
                    $query->where('status', \App\Models\Sender::STATUS_APPROVED);
                    $query->where('sms_sent_before', 0);
                    $query->where(function ($q) use ($checkDate) {
                        // Check contract_expiration_date if it exists
                        $q->whereNotNull('contract_expiration_date')
                            ->where('contract_expiration_date', '<=', $checkDate->format('Y-m-d'))
                            ->orWhere(function ($sq) use ($checkDate) {
                            // Check expire_date if contract_expiration_date doesn't exist
                            $sq->whereNotNull('expire_date')
                                ->where('expire_date', '<=', $checkDate->format('Y-m-d'));
                        });
                    });
                })
                ->whereHas('connector.smsConfiguration', function ($query) {
                    $query->whereNotNull('sender_id');
                })
                ->get();

            $notificationCount = 0;

            foreach ($expiredChannels as $channel) {
                if (!$channel->connector?->workspace?->organization?->owner) {
                    continue;
                }
                $user = $channel->connector->workspace->organization->owner;
                $sender = $channel->connector->smsConfiguration->sender;

                $expirationDate = $sender->contract_expiration_date ?? $sender->expire_date;
                $daysUntilExpiry = Carbon::parse($expirationDate)->diffInDays(Carbon::now());

                $user->notify(new ChannelExpiryNotification(
                    $channel,
                    $channel->connector->workspace,
                    $expirationDate,
                    $daysUntilExpiry
                ));
             
                $channel->connector->smsConfiguration->sender->update(['sms_sent_before'=>1]);
                $notificationCount++;

                Log::info("Expiry notification sent", [
                    'channel_id' => $channel->id,
                    'sender_id' => $sender->id,
                    'expiration_date' => $expirationDate,
                    'days_until_expiry' => $daysUntilExpiry
                ]);

            }

            $this->info("Sent {$notificationCount} channel expiry notifications");
            Log::info("Channel expiry check completed. Sent {$notificationCount} notifications.");

        } catch (\Exception $e) {
            $this->error("Error processing channel expiry notifications: " . $e->getMessage());
            Log::error("Channel expiry notification error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}