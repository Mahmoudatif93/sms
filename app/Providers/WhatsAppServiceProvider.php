<?php

namespace App\Providers;

use App\Domain\WhatsApp\Repositories\WhatsAppMessageRepository;
use App\Domain\WhatsApp\Repositories\WhatsAppMessageRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class WhatsAppServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind Repository Interface to Implementation
        $this->app->bind(
            WhatsAppMessageRepositoryInterface::class,
            WhatsAppMessageRepository::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
