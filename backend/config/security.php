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
