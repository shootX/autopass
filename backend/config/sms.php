<?php

return [

    'driver' => env('SMS_DRIVER', 'mock'),

    'mock_inbox_enabled' => filter_var(env('SMS_MOCK_INBOX_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    // Only texts to these numbers are kept for the admin inbox (default: TEST_PAYMENT_PHONES).
    'mock_inbox_phones' => array_values(array_filter(array_map('trim', explode(',', (string) env('SMS_MOCK_INBOX_PHONES', env('TEST_PAYMENT_PHONES', '')))))),

    'inbox_ttl_minutes' => 10,

    'office' => [
        'key' => env('SMSOFFICE_API_KEY'),
        'sender' => env('SMSOFFICE_SENDER', 'AutoPass'),
        'urgent' => filter_var(env('SMSOFFICE_URGENT', false), FILTER_VALIDATE_BOOLEAN),
        'url' => 'https://smsoffice.ge/api/v2/send/',
        'connect_timeout' => 5,
        'timeout' => 10,
    ],

];
