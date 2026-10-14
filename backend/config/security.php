<?php

return [

    'sms_ttl_minutes' => (int) env('SECURITY_SMS_TTL_MINUTES', 5),

    'sms_max_attempts' => (int) env('SECURITY_SMS_MAX_ATTEMPTS', 5),

    'sms_resend_seconds' => (int) env('SECURITY_SMS_RESEND_SECONDS', 60),

    'password_grant_minutes' => (int) env('SECURITY_PASSWORD_GRANT_MINUTES', 10),

    'temp_password_hours' => (int) env('SECURITY_TEMP_PASSWORD_HOURS', 24),

    'payment_access_minutes' => (int) env('SECURITY_PAYMENT_ACCESS_MINUTES', 15),

    'test_payment_phones' => array_values(array_filter(array_map(
        static fn ($phone) => trim((string) $phone),
        explode(',', (string) env('TEST_PAYMENT_PHONES', ''))
    ))),

];
