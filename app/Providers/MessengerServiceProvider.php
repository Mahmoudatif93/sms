<?php

namespace App\Providers;

use App\Domain\Messenger\Repositories\MessengerMessageRepository;
use App\Domain\Messenger\Repositories\MessengerMessageRepositoryInterface;
use App\Domain\Messenger\Repositories\MessengerTemplateRepository;
use App\Domain\Messenger\Repositories\MessengerTemplateRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class MessengerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(
            MessengerMessageRepositoryInterface::class,
            MessengerMessageRepository::class
        );

        $this->app->bind(
            MessengerTemplateRepositoryInterface::class,
            MessengerTemplateRepository::class
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
