<?php

namespace App\Providers;

use App\Services\Sms\MockSmsGateway;
use App\Services\Sms\SmsConfigurationException;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsOfficeGateway;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MockSmsGateway::class);
        $this->app->singleton(SmsGateway::class, function ($app) {
            return match ((string) config('sms.driver')) {
                'mock' => $app->make(MockSmsGateway::class),
                'smsoffice' => $app->make(SmsOfficeGateway::class),
                default => throw new SmsConfigurationException('Unknown SMS driver'),
            };
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
