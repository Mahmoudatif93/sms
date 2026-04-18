<?php

namespace App\Providers;

use App\Domain\Conversation\Events\LiveChat\LiveChatMessageSent;
use App\Domain\Conversation\Events\LiveChat\LiveChatReactionUpdated;
use App\Domain\Conversation\Events\Widget\WidgetMessageSent;
use App\Domain\Conversation\Events\Widget\WidgetReactionSent;
use App\Domain\Conversation\Events\Widget\WidgetConversationClosed;
use App\Domain\Conversation\Events\Widget\WidgetMessageStatusUpdated;
use App\Domain\Conversation\Listeners\BroadcastLiveChatMessage;
use App\Domain\Conversation\Listeners\BroadcastLiveChatReaction;
use App\Domain\Conversation\Listeners\Widget\BroadcastWidgetMessage;
use App\Domain\Conversation\Listeners\Widget\BroadcastWidgetReaction;
use App\Domain\Conversation\Listeners\Widget\BroadcastWidgetConversationClosed;
use App\Domain\Conversation\Listeners\Widget\BroadcastWidgetMessageStatus;
use App\Events\LiveChatIncomingMessage;
use App\Events\LiveChatStatusUpdated;
use App\Events\WhatsappInteractiveResponseReceived;
use App\Events\WhatsappMessageStatusUpdated;
use App\Events\WhatsappStartConversation;
use App\Listeners\ProcessWhatsappWorkflowListener;
use App\Listeners\ProcessTemplateWorkflowListener;
use App\Services\UnifiedEventHandlerService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Event::listen(WhatsappIncomingMessage::class, function (WhatsappIncomingMessage $event) {
        //     app(UnifiedEventHandlerService::class)->processWhatsappIncomingMessage($event);
        // });

        // Register LiveChat events
        Event::listen(LiveChatIncomingMessage::class, function (LiveChatIncomingMessage $event) {
            app(UnifiedEventHandlerService::class)->processLiveChatIncomingMessage($event);
        });

        Event::listen(LiveChatStatusUpdated::class, function (LiveChatStatusUpdated $event) {
            app(UnifiedEventHandlerService::class)->processLiveChatStatusUpdate($event);
        });

        // LiveChat Domain Events - Broadcasting (Agent side)
        Event::listen(LiveChatMessageSent::class, BroadcastLiveChatMessage::class);
        Event::listen(LiveChatReactionUpdated::class, BroadcastLiveChatReaction::class);

        // Widget Domain Events - Broadcasting (Visitor side)
        Event::listen(WidgetMessageSent::class, BroadcastWidgetMessage::class);
        Event::listen(WidgetReactionSent::class, BroadcastWidgetReaction::class);
        Event::listen(WidgetConversationClosed::class, BroadcastWidgetConversationClosed::class);
        Event::listen(WidgetMessageStatusUpdated::class, BroadcastWidgetMessageStatus::class);

        // Register WhatsApp message status update listener for workflow processing
        // Event::listen(WhatsappMessageStatusUpdated::class, ProcessTemplateWorkflowListener::class);

        // Register WhatsApp interactive response listener for interactive workflow processing
        // Event::listen(WhatsappInteractiveResponseReceived::class, ProcessInteractiveWorkflowListener::class);

        // WhatsappStartConversation is auto-discovered via handleConversationStarted method

    }
}
