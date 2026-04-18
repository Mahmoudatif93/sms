<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\NotificationManagerInterface;
use App\Contracts\NotificationChannelInterface;
use App\Contracts\NotificationPreferenceInterface;
use App\Services\Notifications\NotificationManager;
use App\Services\Notifications\ChannelManager;
use App\Services\Notifications\PreferenceManager;
use App\Services\Notifications\TemplateManager;
// use App\Services\Notifications\NotificationAnalytics;
use App\Services\Notifications\Channels\SmsNotificationChannel;
use App\Services\Notifications\Channels\EmailNotificationChannel;
use App\Services\Notifications\TelegramNotificationChannel;
use App\Services\NotificationService;

class NotificationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register core services
        $this->app->singleton(ChannelManager::class, function ($app) {
            return new ChannelManager();
        });

        $this->app->singleton(PreferenceManager::class, function ($app) {
            return new PreferenceManager();
        });

        $this->app->singleton(TemplateManager::class, function ($app) {
            return new TemplateManager();
        });

        // $this->app->singleton(NotificationAnalytics::class, function ($app) {
        //     return new NotificationAnalytics();
        // });

        // Register main notification manager
        $this->app->singleton(NotificationManagerInterface::class, function ($app) {
            return new NotificationManager(
                $app->make(ChannelManager::class),
                $app->make(PreferenceManager::class),
                $app->make(TemplateManager::class)
            );
        });

        // Register legacy notification service for backward compatibility
        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService();
        });

        // Register Telegram channel as singleton
        $this->app->singleton(TelegramNotificationChannel::class, function ($app) {
            return new TelegramNotificationChannel();
        });

        // Aliases for easier access
        $this->app->alias(NotificationManagerInterface::class, 'notification.manager');
        $this->app->alias(NotificationManagerInterface::class, NotificationManager::class);
        $this->app->bind('notification.service', NotificationService::class);

        // Register preference manager interface
        $this->app->bind(NotificationPreferenceInterface::class, PreferenceManager::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register notification channels
        $this->registerNotificationChannels();

        // Load default templates
        $this->loadDefaultTemplates();

        // Publish configuration and migrations
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/notifications.php' => config_path('notifications.php'),
            ], 'notification-config');

            $this->publishes([
                __DIR__.'/../../database/migrations/' => database_path('migrations'),
            ], 'notification-migrations');
        }
    }

    /**
     * Register notification channels
     */
    protected function registerNotificationChannels(): void
    {
        $channelManager = $this->app->make(ChannelManager::class);

        // Register SMS channel
        $smsService = $this->app->make(\App\Services\SendLoginNotificationService::class);
        $channelManager->registerChannel('sms', new SmsNotificationChannel($smsService));

        // Register Email channel
        $emailService = $this->app->make(\App\Services\SendLoginNotificationService::class);
        $channelManager->registerChannel('email', new EmailNotificationChannel($emailService));

        // Register Telegram channel
        if (config('notifications.available_channels.telegram.bot_token')) {
            $channelManager->registerChannel('telegram', new TelegramNotificationChannel());
        }else{
            \Log::warning('Telegram bot token is not configured. Telegram channel not registered.');
        }

    }

    /**
     * Load default templates
     */
    protected function loadDefaultTemplates(): void
    {
        $templateManager = $this->app->make(TemplateManager::class);
        $templateManager->loadDefaultTemplates();
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            NotificationManagerInterface::class,
            NotificationManager::class,
            ChannelManager::class,
            PreferenceManager::class,
            TemplateManager::class,
            // NotificationAnalytics::class,
            NotificationService::class,
            TelegramNotificationChannel::class,
            'notification.manager',
            'notification.service',
        ];
    }
}
