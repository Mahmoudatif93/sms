<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;

class WhatsappChatListEntry extends DataInterface
{

    private $consumerPhoneNumber;
    private $last_message;
    private $timestamp;
    private $unread_notification_count;



    public function __construct()
    {
    }


}
