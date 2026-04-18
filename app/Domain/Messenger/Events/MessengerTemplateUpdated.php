<?php

namespace App\Domain\Messenger\Events;

use App\Models\MessengerTemplate;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessengerTemplateUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly MessengerTemplate $template
    ) {}
}
