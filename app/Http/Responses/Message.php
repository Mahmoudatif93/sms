<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;


class Message extends DataInterface
{
    public ?int $id;
    public  $channel;
    public  $user_id;
    public  $workspace_id;
    public  $text;
    public  $sending_datetime;
    public  $creation_datetime;
    public  $variables_message;
    public  $transmission_rate;
    public  $count;
    public  $cost;
    public  $sender_name;
    public  $status;
    public int $length;

    public function __construct(\App\Models\Message $message)
    {
        $this->id = $message->id;
        $this->channel = $message->channel;
        $this->user_id = $message->user_id;
        $this->workspace_id = $message->workspace_id;
        $this->text = $message->text;
        $this->creation_datetime = $message->creation_datetime;
        $this->sending_datetime = $message->sending_datetime == null ? $message->creation_datetime:$message->sending_datetime ;
        $this->variables_message = $message->variables_message;
        $this->count = $message->count;
        $this->cost = $message->cost;
        $this->length = $message->length;
        $this->sender_name = $message->sender_name;
        $this->status = $message->status;
        $this->transmission_rate = ceil(($message->sent_cnt/$message->count)*100) .'%';

    }
}
