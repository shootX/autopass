<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //URL::forceScheme('https');

        // OTP / SMS endpoints: per IP and per phone, so one number can't be spammed.
        RateLimiter::for('otp', fn (Request $request) => [
            Limit::perMinute(5)->by('otp-ip:'.$request->ip()),
            Limit::perHour(20)->by('otp-phone:'.((string) $request->input('phone', '')).'|'.$request->ip()),
        ]);

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(10)->by('login-ip:'.$request->ip()),
            Limit::perMinute(5)->by('login-id:'.strtolower((string) ($request->input('phone') ?? $request->input('login') ?? ''))),
        ]);

        RateLimiter::for('voucher', fn (Request $request) =>
            Limit::perMinute(20)->by('voucher:'.(auth('partners')->id() ?? auth('api')->id() ?? $request->ip())));

        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
    }
}
