<?php
namespace App\Class;

use App\Http\Interfaces\Sms\SmsProcessor;
use App\Jobs\PrepareMessageDetails;
use App\Jobs\StartMessageSendingJob;
use App\Models\Message;

class LargeSmsProcessor extends SmsProcessor
{
    public function process($outbox)
    {
        // Chain jobs: First prepare message details, then start sending
        $prepareJob = new PrepareMessageDetails($outbox, $this->sms);
        $sendJob = new StartMessageSendingJob($outbox->message, $this->sms, $this);
        // Chain the jobs so sending starts only after preparation is complete
        dispatch($prepareJob->chain([$sendJob]))->onQueue('sms-normal');

        return true;
    }

    public function StartMessageSend(Message $message)
    {
        parent::StartMessageSend($message);
    }

    public function sendMessage($message_id)
    {
        $this->sms::sendCampaign($message_id, 0, 0);
    }
}