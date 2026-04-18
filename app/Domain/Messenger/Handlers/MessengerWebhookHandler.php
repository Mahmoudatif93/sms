<?php

namespace App\Domain\Messenger\Handlers;

use App\Domain\Messenger\Services\MessengerWebhookService;

class MessengerWebhookHandler
{
    private array $notification;

    public function __construct(array $notification)
    {
        $this->notification = $notification;
    }

    /**
     * Handle the webhook notification
     */
    public function handle(): void
    {
        app(MessengerWebhookService::class)->handleNotification($this->notification);
    }
}
