<?php
namespace App\Class;

use App\Http\Interfaces\Sms\SmsProcessor;
use App\Models\MessageDetails;
use App\Services\Sms;
use App\Models\Message;

class SmallSmsProcessor extends SmsProcessor
{

    public function process($outbox)
    {
        $numbers = $outbox->all_numbers;
        $numbers = json_decode($numbers);
        foreach ($numbers as $number) {
            $details_param[] = [
                'message_id' => $outbox->message_id,
                'text' => $outbox->variables_message == 1? $number->text : null,
                'length' => $outbox->length,
                'number' => $number->number,
                'country_id' => $number->country,
                'operator_id' => 0,
                'cost' => $number->cost,
                'status' => 0,
                'encrypted' => $outbox->encrypted,
                'key' => bin2hex(random_bytes(8)),
                'gateway_id' => 0,
                'created_at' => \Carbon\Carbon::now()
            ];
        }
        if (MessageDetails::insert($details_param)) {
            $message = $outbox->message;
            $this->StartMessageSend($message);
            // check if file and deleted 
            $outbox->delete();
            return true;
        }
        return false;

    }

    public function StartMessageSend(Message $message)
    {
        parent::StartMessageSend($message);
    }

    public function sendMessage($message_id) {
        $this->sms::sendCampaign($message_id, 0, 0);
    }
}