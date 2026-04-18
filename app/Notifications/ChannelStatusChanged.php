<?php

namespace App\Notifications;

use App\Models\Channel;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChannelStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    protected $channel;
    protected $organization;
    protected $status;

    /**
     * Create a new notification instance.
     *
     * @param  Channel  $channel
     * @param  Organization  $organization
     * @param  string  $status  'approved', 'rejected', 'payment_required'
     * @return void
     */
    public function __construct(Channel $channel, Organization $organization, string $status)
    {
        $this->channel = $channel;
        $this->organization = $organization;
        $this->status = $status;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        // Use email and SMS if number is available
        $via = ['mail', 'database'];

        if ($notifiable->number) {
            $via[] = 'sms';
        }

        return $via;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return MailMessage
     */
    public function toMail($notifiable)
    {
        $locale = $notifiable->lang ?? config('app.locale');
        app()->setLocale($locale);
        $direction = $locale === 'ar' ? 'rtl' : 'ltr';
        $alignment = $locale === 'ar' ? 'right' : 'left';

        $subject = trans("notification.email.channel.status.{$this->status}.subject");
        $title = trans("notification.email.channel.status.{$this->status}.title");
        $greeting = trans("notification.email.channel.status.{$this->status}.greeting", ['name' => $notifiable->name]);
        $line1 = trans("notification.email.channel.status.{$this->status}.line1", [
            'channel_name' => $this->channel->name,
            'org_name' => $this->organization->name
        ]);
        $line2 = trans("notification.email.channel.status.{$this->status}.line2");
        $actionText = trans("notification.email.channel.status.{$this->status}.action");
        $thanks = trans("notification.email.channel.status.{$this->status}.thanks");

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.channel-status', [
                'subject' => $subject,
                'title' => $title,
                'greeting' => $greeting,
                'line1' => $line1,
                'line2' => $line2,
                'actionText' => $actionText,
                'actionUrl' => return_front_url('/dashboard'),
                'thanks' => $thanks,
                'direction' => $direction,
                'alignment' => $alignment
            ]);
    }

    /**
     * Get the SMS representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return string
     */
    public function toSms($notifiable)
    {
        $locale = $notifiable->lang ?? config('app.locale');
        app()->setLocale($locale);
        return trans("notification.sms.channel.status.{$this->status}", [
            'channel_name' => $this->channel->name,
            'org_name' => $this->organization->name
        ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'channel_id' => $this->channel->id,
            'channel_name' => $this->channel->name,
            'organization_id' => $this->organization->id,
            'organization_name' => $this->organization->name,
            'status' => $this->status,
            'action' => 'channel_status_changed'
        ];
    }
}