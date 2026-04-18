<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\MessageDetails;
use App\Models\AdminMessageDetails;
use App\Events\SmsFaileEvent;
class SmsFaileListener
{
     /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(SmsFaileEvent $smsFaileEvent): void
    {
      $messageDetailsModel = $smsFaileEvent->model == "admin_message" ? new AdminMessageDetails() : new MessageDetails();
      MessageDetails::BackNumber($smsFaileEvent->message_id,$smsFaileEvent->numbers);
      \Log::error('error in send message ('.$smsFaileEvent->message_id.') to filter api');
    }
}

