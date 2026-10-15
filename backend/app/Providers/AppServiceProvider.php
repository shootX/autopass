<?php

namespace App\Providers;

use App\Services\Sms\MockSmsGateway;
use App\Services\Sms\SmsConfigurationException;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsOfficeGateway;
use App\Support\Phone;
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
        // URL::forceScheme('https');

        // OTP / SMS endpoints: per IP and per phone, so one number can't be spammed.
        RateLimiter::for('otp', fn (Request $request) => [
            Limit::perMinute(5)->by('otp-ip:'.$request->ip()),
            Limit::perHour(20)->by('otp-phone:'.((string) $request->input('phone', '')).'|'.$request->ip()),
        ]);

        // Endpoints that send an SMS: on top of 'otp', a per-number limit that ignores the IP
        // (proxy pools can't pump one number) and a global daily budget for the SMS bill.
        RateLimiter::for('otp-send', function (Request $request) {
            $limits = [
                Limit::perMinute(5)->by('otp-ip:'.$request->ip()),
                Limit::perDay((int) config('security.otp_daily_global_limit'))->by('otp-global'),
            ];
            $phone = substr(Phone::digits((string) $request->input('phone', '')), -9);
            if ($phone !== '') {
                $limits[] = Limit::perHour((int) config('security.otp_per_phone_hourly'))->by('otp-number:'.$phone);
            }

            return $limits;
        });

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(10)->by('login-ip:'.$request->ip()),
            Limit::perMinute(5)->by('login-id:'.strtolower((string) ($request->input('phone') ?? $request->input('login') ?? ''))),
        ]);

        RateLimiter::for('voucher', fn (Request $request) => Limit::perMinute(20)->by('voucher:'.(auth('partners')->id() ?? auth('api')->id() ?? $request->ip())));

        RateLimiter::for('referral', fn (Request $request) => Limit::perMinute(10)->by('referral:'.$request->ip()));

        RateLimiter::for('refresh', fn (Request $request) => [
            Limit::perMinute(10)->by('refresh:'.hash('sha256', (string) ($request->cookie('ap_refresh') ?: $request->bearerToken() ?: ''))),
            Limit::perMinute(60)->by('refresh-ip:'.$request->ip()),
        ]);

        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
    }
}
