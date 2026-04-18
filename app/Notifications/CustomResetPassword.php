<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use App\Jobs\SendEmail;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;

class CustomResetPassword extends Notification
{
    public $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function via($notifiable)
    {
        return ['mail']; // still a mail notification but customized
    }

    public function toMail($notifiable)
    {
        // Build the reset URL (adjust the URL if needed)
        $message = url(route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()], false));

        // Dispatch your SendEmail job here to send the email asynchronously
        SendEmail::dispatch(
            $notifiable->email,
            'Password Reset Request',
            'Password Reset',
            '',
            'mail.reset_password',
            [],
            [],
            [],
            [],
            $message
        );


        return (new MailMessage)
            ->subject('Password Reset Email')
            ->line('We have sent a password reset email to your inbox.');
    }
}
