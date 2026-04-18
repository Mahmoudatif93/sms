<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ManagementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $subject;
    protected $messageContent;
    protected $data;
    protected $type;
    protected $actionUrl;
    protected $actionText;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        string $subject,
        string $message,
        array $data = [],
        string $type = 'general',
        ?string $actionUrl = null,
        ?string $actionText = null
    ) {
        $this->subject = $subject;
        $this->messageContent = $message;
        $this->data = $data;
        $this->type = $type;
        $this->actionUrl = $actionUrl;
        $this->actionText = $actionText;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->lang ?? config('app.locale');
        app()->setLocale($locale);
        
        $direction = $locale === 'ar' ? 'rtl' : 'ltr';
        $alignment = $locale === 'ar' ? 'right' : 'left';
        
        $translatedSubject = __("notification.email.management.{$this->type}.subject", [
            'subject' => $this->subject
        ]);
        
        $mailMessage = (new MailMessage)
            ->subject($translatedSubject)
            ->view('emails.management-notification', [
                'title' => __("notification.email.management.{$this->type}.title", ['type' => ucfirst($this->type)]),
                'greeting' => __('notification.greeting'),
                'messageContent' => $this->messageContent,
                'data' => $this->data,
                'type' => $this->type,
                'thankYou' => __('notification.thank_you'),
                'signature' => __('notification.signature'),
                'direction' => $direction,
                'alignment' => $alignment
            ]);
        
        if ($this->actionUrl && $this->actionText) {
            $mailMessage->action($this->actionText, $this->actionUrl);
        }
        
        return $mailMessage;
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'subject' => $this->subject,
            'message' => $this->messageContent,
            'data' => $this->data,
            'type' => $this->type,
            'action_url' => $this->actionUrl,
            'action_text' => $this->actionText,
            'created_at' => now()->toIso8601String(),
            'status' => 'unread'
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
            'subject' => $this->subject,
            'message' => $this->messageContent,
            'data' => $this->data,
            'type' => $this->type,
            'action_url' => $this->actionUrl,
            'action_text' => $this->actionText
        ];
    }
}