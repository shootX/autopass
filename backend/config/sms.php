<?php

return [

    'driver' => env('SMS_DRIVER', 'mock'),

    'mock_inbox_enabled' => filter_var(env('SMS_MOCK_INBOX_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

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
