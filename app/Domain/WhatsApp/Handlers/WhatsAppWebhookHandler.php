<?php

namespace App\Domain\WhatsApp\Handlers;

use App\Domain\WhatsApp\Services\WhatsAppWebhookService;

class WhatsAppWebhookHandler
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
        app(WhatsAppWebhookService::class)->handleNotification($this->notification);
    }
}
