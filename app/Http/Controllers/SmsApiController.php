<?php

namespace App\Http\Controllers;

use App\Services\Sms;
use App\Models\Message;
use App\Models\AdminMessage;
use App\Models\MessageDetails;
use App\Models\AdminMessageDetails;
use OpenApi\Annotations as OA;


class SmsApiController extends BaseApiController
{
    protected $sms;
    public function __construct(SMS $sms)
    {
        $this->sms = $sms;
    }

    public function sendSmsAdmin($senderName, $allNumbers, $message, $gateway_id = 0)
    {
        $this->sms->sendMessage(AdminMessage::class, AdminMessageDetails::class, $senderName, $allNumbers, $message);
    }

    public function sendSmsUser($userId, $senderName, $allNumbers, $message, $gatewayId = 0, $arrayParams = null)
    {
        $this->sms->sendMessage(Message::class, MessageDetails::class, $senderName, $allNumbers, $message, $arrayParams, 'NORMAL', $userId);
    }
}
