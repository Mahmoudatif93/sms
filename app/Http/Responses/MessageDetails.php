<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;


class MessageDetails extends DataInterface
{
    public int $id;
    public string $number;
    public string $sender_name;
    public string $text;
    public int $cost;
    public $status;
    public $length;
    public $created_at;
    public $updated_at;
  

    public function __construct(\App\Models\MessageDetails $messageDetails)
    {
        $this->id = $messageDetails->id;
        $this->number = $messageDetails->number;
        $this->sender_name = $messageDetails->message->sender_name;
        $this->text = $messageDetails->message->variables_message  == 1? $messageDetails->text :  $messageDetails->message->text;
        $this->cost = $messageDetails->cost;
        $this->status = $messageDetails->status;
        $this->length =  $messageDetails->message->variables_message  == 1? $messageDetails->length :  $messageDetails->message->length;
        $this->created_at = $messageDetails->created_at;
        $this->updated_at = $messageDetails->updated_at;    
    }
}
