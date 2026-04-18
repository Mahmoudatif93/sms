<?php
namespace App\Notifications;
use App\Models\Channel;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use App\Services\Sms;

class ChannelDisabledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $channel;
    protected $workspace;
    protected $expirationDate;

    public function __construct(Channel $channel, Workspace $workspace, string $expirationDate)
    {
        $this->channel = $channel;
        $this->workspace = $workspace;
        $this->expirationDate = $expirationDate;
    }

    public function via($notifiable)
    {
        $via = ['mail','database'];
        
        if ($notifiable->number) {
            $via[] = 'sms';
        }
        
        return $via;
    }

    public function toMail($notifiable)
    {
        $locale = $notifiable->lang ?? config('app.locale');
        app()->setLocale($locale);
        $subject = __('notification.email.channel.disabled.subject', [
            'channel' => $this->channel->name
        ]);
        $line1 = __('notification.email.channel.disabled.channel_disabled', [
            'channel' => $this->channel->name
        ]);
        $line2= __('notification.email.channel.disabled.expiration_date', [
            'date' => $this->expirationDate
        ]);
        $line3 = __('notification.contact_support');
        $actionText = trans("notification.view_channel");
        $actionUrl =  return_front_url('/channels/sms-info/{$this->channel->id}');

       
    }

    public function toSms($notifiable)
    {
        $locale = $notifiable->lang ?? config('app.locale');
        app()->setLocale($locale);
        $message = __('notification.sms.channel.disabled.alert', [
            'channel' => $this->channel->name,
            'date' => $this->expirationDate
        ]);
        return $message;
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => "Channel Disaple - {$this->channel->name}",
            'channel_id' => $this->channel->id,
            'channel_name' => $this->channel->name,
            'workspace_id' => $this->workspace->id,
            'workspace_name' => $this->workspace->name,
            'platform' => $this->channel->platform,
            'type' => 'channel_expiry',
            'status' => 'unread',
            'created_at' => now()->toIso8601String()
        ];
    }
}