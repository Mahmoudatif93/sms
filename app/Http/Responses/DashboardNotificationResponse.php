<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;
use App\Models\DashboardNotification;

class DashboardNotificationResponse extends DataInterface
{

    public int $id;
    public string $icon;
    public ?string $link;
    public ?string $title;
    public ?string $message;
    public ?string $category;
    public ?string $notifiable_id;
    public ?string $notifiable_type;
    public ?string $reviewId;
    public ?string $channel_id;
    public ?int $read_at;
    public ?bool $is_read;


    public function __construct(DashboardNotification $notification)
    {
        $this->id = $notification->id;
        $this->title = $notification->title;
        $this->message = $notification->message;
        $this->icon =  $notification->icon;
        $this->link = $notification->link;
        $this->category = $notification->category;
        $this->notifiable_id = $notification->notifiable_id;
        $this->read_at = $notification->read_at;
        $this->is_read = !empty($notification->read_at);
        $this->notifiable_type = $notification->notifiable_type;
        if($notification->notifiable_type === 'App\Models\StatisticsProcessing'){
            $this->reviewId = $notification->notifiable?->processing_id;
            $this->channel_id = $notification->notifiable?->channel_id;
        }

    }
}
