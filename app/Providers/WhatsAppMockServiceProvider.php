<?php

namespace App\Providers;

use App\Services\WhatsAppMockService;
use Illuminate\Support\ServiceProvider;

class WhatsAppMockServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // تسجيل المحاكي كـ singleton في الحاوي
        $this->app->singleton(WhatsAppMockService::class, function ($app) {
            return new WhatsAppMockService();
        });

        // تسجيل alias للوصول السهل
        $this->app->alias(WhatsAppMockService::class, 'whatsapp.mock');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // يمكن إضافة أي تكوينات إضافية هنا إذا لزم الأمر
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            WhatsAppMockService::class,
            'whatsapp.mock'
        ];
    }
}
