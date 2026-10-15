<?php

return [

    'sms_ttl_minutes' => (int) env('SECURITY_SMS_TTL_MINUTES', 5),

    'sms_max_attempts' => (int) env('SECURITY_SMS_MAX_ATTEMPTS', 5),

    // Partner voucher check: identical checks inside this window reuse the open SMS challenge.
    'voucher_check_dedupe_seconds' => (int) env('SECURITY_VOUCHER_CHECK_DEDUPE_SECONDS', 60),

    'sms_resend_seconds' => (int) env('SECURITY_SMS_RESEND_SECONDS', 60),

    'password_grant_minutes' => (int) env('SECURITY_PASSWORD_GRANT_MINUTES', 10),

    'temp_password_hours' => (int) env('SECURITY_TEMP_PASSWORD_HOURS', 24),

    'payment_access_minutes' => (int) env('SECURITY_PAYMENT_ACCESS_MINUTES', 15),

    // Test (sandbox) payments in APP_ENV=production. Default off. Even when on, only admins and TEST_PAYMENT_PHONES.
    // Upper bound for one sandbox top-up (points).
    'test_payment_max_amount' => (int) env('TEST_PAYMENT_MAX_AMOUNT', 100),
    // Upper bound per user per day across all sandbox top-ups (points).
    'test_payment_daily_max' => (int) env('TEST_PAYMENT_DAILY_MAX', 300),

    // Absolute lifetime of a login across /api/refresh rotations (minutes).
    'session_max_minutes' => (int) env('SECURITY_SESSION_MAX_MINUTES', 43200),

    // Refresh cookie Secure flag. Keep true outside local http development.
    // A rotated refresh token still works this long (two tabs refreshing at once).
    'refresh_reuse_grace_seconds' => (int) env('REFRESH_REUSE_GRACE_SECONDS', 30),

    'refresh_cookie_secure' => (bool) env('REFRESH_COOKIE_SECURE', true),

    // OTP SMS: per phone (any IP) per hour, and for the whole app per day.
    'otp_per_phone_hourly' => (int) env('SECURITY_OTP_PER_PHONE_HOURLY', 5),
    'otp_daily_global_limit' => (int) env('SECURITY_OTP_DAILY_GLOBAL_LIMIT', 2000),

    // Comma-separated proxy IPs/CIDRs whose X-Forwarded-* headers are trusted ('*' = any).
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    'test_payment_allow_production' => filter_var(env('TEST_PAYMENT_ALLOW_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN),

    'test_payment_phones' => array_values(array_filter(array_map(
        static fn ($phone) => trim((string) $phone),
        explode(',', (string) env('TEST_PAYMENT_PHONES', ''))
    ))),

];
