<?php
namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Sender;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Notifications\ChannelDisabledNotification;

class DisableExpiredChannel extends Command
{
    protected $signature = 'channels:disable-expired';
    protected $description = 'Disable senders that have reached their expiration date';

    public function handle()
    {
        $today = Carbon::now()->format('Y-m-d');

        try {
            $channelsToDisable = Channel::where('platform', Channel::SMS_PLATFORM)
                ->where('status', Channel::STATUS_ACTIVE)
                ->with([
                    'connector.smsConfiguration.sender',
                ])
                ->whereHas('connector.smsConfiguration.sender', function ($query) use ($today) {
                    $query->where('status', Sender::STATUS_APPROVED)
                        ->where(function ($q) use ($today) {
                            $q->whereNotNull('contract_expiration_date')
                                ->where('contract_expiration_date', '<=', $today)
                                ->orWhere(function ($sq) use ($today) {
                                    $sq->whereNotNull('expire_date')
                                        ->where('expire_date', '<=', $today);
                                });
                        });
                })
                ->whereHas('connector.smsConfiguration', function ($query) {
                    $query->whereNotNull('sender_id');
                })
                ->get();

            $disabledCount = 0;

            foreach ($channelsToDisable as $channel) {
                $sender = $channel->connector->smsConfiguration->sender;
                $user = $channel->connector->workspace->organization->owner;
                $sender->update([
                    'status' => Sender::STATUS_EXPIRED
                ]);
                
                $user->notify(new ChannelDisabledNotification(
                    $channel,
                    $channel->connector->workspace,
                    $today
                ));
                
                $disabledCount++;

                Log::info("Sender disabled due to expiration", [
                    'sender_id' => $sender->id,
                    'channel_id' => $channel->id,
                    'expiration_date' => $sender->contract_expiration_date ?? $sender->expire_date
                ]);
            }

            $this->info("Disabled {$disabledCount} expired senders");
            Log::info("Sender disable process completed", [
                'senders_disabled' => $disabledCount
            ]);

        } catch (\Exception $e) {
            $this->error("Error disabling expired senders: " . $e->getMessage());
            Log::error("Sender disable error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}