<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\RecaptchaService;

class RecaptchaServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(RecaptchaService::class, function ($app) {
            return new RecaptchaService([
                'recaptcha_site_key' => config('services.recaptcha.site_key'),
                'recaptcha_secret_key' => config('services.recaptcha.secret_key'),
                'recaptcha_lang' => config('services.recaptcha.lang'),
            ]);
        });
    }

    public function boot()
    {
        //
    }
}
