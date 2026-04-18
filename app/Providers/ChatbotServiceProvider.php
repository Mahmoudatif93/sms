<?php

namespace App\Providers;

use App\Domain\Chatbot\Repositories\ChatbotRepository;
use App\Domain\Chatbot\Repositories\ChatbotRepositoryInterface;
use App\Domain\Chatbot\Services\ChatbotAIService;
use App\Domain\Chatbot\Services\ChatbotService;
use App\Domain\Chatbot\Services\KnowledgeSearchService;
use Illuminate\Support\ServiceProvider;

class ChatbotServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind Repository Interface
        $this->app->bind(
            ChatbotRepositoryInterface::class,
            ChatbotRepository::class
        );

        // Register Services as singletons
        $this->app->singleton(KnowledgeSearchService::class, function ($app) {
            return new KnowledgeSearchService(
                $app->make(ChatbotRepositoryInterface::class)
            );
        });

        $this->app->singleton(ChatbotAIService::class, function ($app) {
            return new ChatbotAIService();
        });

        $this->app->singleton(ChatbotService::class, function ($app) {
            return new ChatbotService(
                $app->make(ChatbotRepositoryInterface::class),
                $app->make(KnowledgeSearchService::class),
                $app->make(ChatbotAIService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
