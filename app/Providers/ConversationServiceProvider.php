<?php

namespace App\Providers;

use App\Domain\Conversation\Channels\InstagramChannel;
use App\Domain\Conversation\Channels\LiveChatChannel;
use App\Domain\Conversation\Channels\MessengerChannel;
use App\Domain\Conversation\Channels\TelegramChannel;
use App\Domain\Conversation\Channels\WhatsAppChannel;
use App\Domain\Conversation\Repositories\ConversationRepository;
use App\Domain\Conversation\Repositories\ConversationRepositoryInterface;
use App\Domain\Conversation\Repositories\LiveChatMessageRepository;
use App\Domain\Conversation\Repositories\LiveChatMessageRepositoryInterface;
use App\Domain\Conversation\Repositories\WhatsAppMessageRepository;
use App\Domain\Conversation\Repositories\WhatsAppMessageRepositoryInterface;
use App\Domain\Conversation\Services\ChannelResolver;
use App\Domain\Conversation\Services\ConversationService;
use App\Domain\Conversation\Services\LiveChatMessageService;
use App\Domain\Conversation\Services\LiveChatWidgetService;
use App\Domain\Conversation\Services\MessageTranslationService;
use App\Domain\Conversation\Services\WhatsAppMessageService;
use App\Domain\Conversation\Repositories\WidgetRepository;
use App\Domain\Conversation\Repositories\WidgetRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class ConversationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Repository bindings
        $this->app->bind(
            ConversationRepositoryInterface::class,
            ConversationRepository::class
        );

        $this->app->bind(
            WhatsAppMessageRepositoryInterface::class,
            WhatsAppMessageRepository::class
        );

        $this->app->bind(
            LiveChatMessageRepositoryInterface::class,
            LiveChatMessageRepository::class
        );

        $this->app->bind(
            WidgetRepositoryInterface::class,
            WidgetRepository::class
        );

        // Channel singletons
        $this->app->singleton(WhatsAppChannel::class);
        $this->app->singleton(LiveChatChannel::class);
        $this->app->singleton(MessengerChannel::class);
        $this->app->singleton(TelegramChannel::class);
        $this->app->singleton(InstagramChannel::class);

        // Message Services (depends on Actions which depend on Repository)
        $this->app->singleton(WhatsAppMessageService::class);
        $this->app->singleton(LiveChatMessageService::class);
        $this->app->singleton(LiveChatWidgetService::class);

        // Services
        $this->app->singleton(ChannelResolver::class, function ($app) {
            return new ChannelResolver(
                $app->make(WhatsAppChannel::class),
                $app->make(LiveChatChannel::class),
                $app->make(MessengerChannel::class),
                $app->make(TelegramChannel::class),
                $app->make(InstagramChannel::class)
            );
        });

        $this->app->singleton(ConversationService::class);
        $this->app->singleton(MessageTranslationService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
