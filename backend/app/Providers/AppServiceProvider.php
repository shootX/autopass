<?php

namespace App\Providers;

use App\Services\Security\FakeSmsSender;
use App\Services\Security\HttpSmsSender;
use App\Services\Security\SmsSender;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FakeSmsSender::class);
        $this->app->singleton(SmsSender::class, function ($app) {
            if ($app->environment('testing')) {
                return $app->make(FakeSmsSender::class);
            }

            return $app->make(HttpSmsSender::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //URL::forceScheme('https');
    }
}
