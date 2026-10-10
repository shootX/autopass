<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'carapi' => [
        'url' => env('CARAPI_URL', 'https://car.socialsave.cc/api/v1'),
        'secret' => env('CARAPI_SECRET'),
    ],

    'smsoffice' => [
        'key' => env('SMSOFFICE_API_KEY'),
        'sender' => env('SMSOFFICE_SENDER'),
    ],

    'flitt' => [
        'merchant_id' => env('FLITT_PAY_NUMBER', '1549901'),
        'secret' => env('FLITT_PAYMENT_KEY', 'test'),
    ],

    'tbc' => [
        'base_url' => env('TBC_BASE_URL', 'https://test-api.tbcbank.ge'),
        'api_key' => env('TBC_API_KEY'),
        'client_id' => env('TBC_CLIENT_ID'),
        'client_secret' => env('TBC_CLIENT_SECRET'),
    ],

];
