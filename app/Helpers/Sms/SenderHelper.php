<?php

namespace App\Helpers\Sms;

class SenderHelper
{
    public static function isAdSender($sender_name)
    {
        return strpos($sender_name, '-AD') !== false;
    }
}