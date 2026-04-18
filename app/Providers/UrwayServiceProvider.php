<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\UrwayService;

class UrwayServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(UrwayService::class, function ($app) {
            return new UrwayService();
        });
    }

    public function boot()
    {
        //
    }
}
