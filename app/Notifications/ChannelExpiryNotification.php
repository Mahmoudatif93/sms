<?php

namespace App\Notifications;

use App\Models\Channel;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Services\Sms;

class ChannelExpiryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $channel;
    protected $workspace;
    protected $expirationDate;
    protected $daysUntilExpiry;

    /**
     * Create a new notification instance.
     */
    public function __construct(Channel $channel, Workspace $workspace,$expirationDate, int $daysUntilExpiry)
    {
        $this->channel = $channel;
        $this->workspace = $workspace;
        $this->expirationDate = $expirationDate;
        $this->daysUntilExpiry = $daysUntilExpiry;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $via = ['mail','database'];
        if ($notifiable->number) {
            $via[] = 'sms';
        }
        
        return $via;
    }

    /**
     * Send the SMS notification
     */
    public function toSms($notifiable)
    {

        $locale = $notifiable->lang ?? config('app.locale');
        \Log::info($locale);
        app()->setLocale($locale);
        
        $message = __('notification.sms.channel.expiry.alert', [
            'channel' => $this->channel->name,
            'days' => $this->daysUntilExpiry
        ]);
        return $message;
    }
    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->lang ?? config('app.locale');
        app()->setLocale($locale);
        return (new MailMessage)
            ->subject(__('notification.email.channel.expiry.subject',['channel' => $this->channel->name]))
            ->line(__('notification.email.channel.expiry.channel_expiry',[
                'channel' => $this->channel->name,
                'workspace' => $this->workspace->name,
                'days' => $this->daysUntilExpiry
            ]))
            ->line(__('notification.email.channel.expiry.expiration_date', ['date' => $this->expirationDate]))
            // ->line(__('notification.platform', ['platform' => $this->channel->platform]))
            ->line(__('notification.email.channel.expiry.action_needed'))
            ->action(__('notification.view_channel'), "https://portal.dreams.sa/channels/sms-info/{$this->channel->id}");
    }

      /**
     * Get the array representation of the notification for storage.
     */
    public function toDatabase($notifiable): array
    {
        return [
            'title' => "Channel Expiring Soon - {$this->channel->name}",
            'channel_id' => $this->channel->id,
            'channel_name' => $this->channel->name,
            'workspace_id' => $this->workspace->id,
            'workspace_name' => $this->workspace->name,
            'days_until_expiry' => $this->daysUntilExpiry,
            'platform' => $this->channel->platform,
            'type' => 'channel_expiry',
            'status' => 'unread',
            'created_at' => now()->toIso8601String()
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'channel_id' => $this->channel->id,
            'channel_name' => $this->channel->name,
            'workspace_id' => $this->workspace->id,
            'workspace_name' => $this->workspace->name,
            'days_until_expiry' => $this->daysUntilExpiry,
            'platform' => $this->channel->platform,
        ];
    }
}