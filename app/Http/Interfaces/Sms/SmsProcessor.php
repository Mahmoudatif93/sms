<?php
namespace App\Http\Interfaces\Sms;
use App\Models\Message;
use App\Services\Sms;

abstract class SmsProcessor {
    public $sms;
    public function __construct(Sms $sms)
    {
        $this->sms = $sms;
    }
    abstract public function process($outbox);

    abstract public function sendMessage($message);
    protected  function StartMessageSend(Message $message)
    {
        if(empty($message->sending_datetime) && $message->advertising == 0){
            Message::where('id', $message->id)
            ->update([
                'status' => 1
            ]);
        }
       
    }

}
