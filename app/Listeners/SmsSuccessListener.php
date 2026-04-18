<?php

namespace App\Listeners;

use App\Events\SmsSuccessEvent;
use App\Models\AdminMessageDetails;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\GatewayResult;
use App\Models\MessageDetails;
use App\Models\Message;
use App\Models\AdminMessage;
class SmsSuccessListener
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
    public function handle(SmsSuccessEvent $smsSuccessEvent): void
    {
        $res = $this->check_result($smsSuccessEvent->response, $smsSuccessEvent->gateway_id);
        $messageDetailsModel = $smsSuccessEvent->model == "admin_message" ? new AdminMessageDetails() : new MessageDetails();
        $messageDetailsModel->SendByNumbers($smsSuccessEvent->message_id, $smsSuccessEvent->numbers, $smsSuccessEvent->gateway_id, $res);
     
        $messageModel = $smsSuccessEvent->model == "admin_message" ? new AdminMessage() : new Message();
        $messageModel->refreshStatus($smsSuccessEvent->message_id);
    }


    private function check_result($message_result, $gateway_id = null)
    {

        $res = null;
        $results = GatewayResult::where('gateway_id', $gateway_id)->get();

        foreach ($results as $gateway_result) {
            preg_match("/" . $gateway_result['value'] . "/i", $message_result, $match);
            if (!empty($match)) {

                $res = $gateway_result['id'];
                break;
            }
        }


        return $res;
    }
}
