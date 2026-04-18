<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use App\Services\Sms;

class SmsChannel
{
    protected $smsService;

    public function __construct(Sms $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        if (!method_exists($notification, 'toSms')) {
            return;
        }
        $message = $notification->toSms($notifiable);
        if (empty($message)) {
            \Log::info('empty sms');
            return;
        }
        // Get the phone number from the notifiable entity
        $phoneNumber = null;
        if (method_exists($notifiable, 'routeNotificationForSms')) {
            $phoneNumber = $notifiable->routeNotificationForSms($notification);
        } else {
            $phoneNumber = $notifiable->number ?? $notifiable->phone;
        }
        
        if (empty($phoneNumber)) {
            \Log::info('empty phoneNumber');
            return;
        }
        \Log::info($phoneNumber);
        $this->smsService->sendMessage(
            \App\Models\AdminMessage::class,
            \App\Models\AdminMessageDetails::class,
            'Dreams',
            $phoneNumber,
            $message
        );
    }
}