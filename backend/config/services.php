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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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

    'piprapay' => [
        'enabled' => env('PIPRAPAY_ENABLED', true),
        'base_url' => env('PIPRAPAY_BASE_URL'),
        'api_key' => env('PIPRAPAY_API_KEY'),
        'currency' => env('PIPRAPAY_CURRENCY', 'BDT'),
        'webhook_url' => env('PIPRAPAY_WEBHOOK_URL'),
        'return_url' => env('PIPRAPAY_RETURN_URL'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
    ],

    'meta' => [
        'pixel_enabled' => env('META_PIXEL_ENABLED', false),
        'pixel_id' => env('META_PIXEL_ID'),
        'capi_enabled' => env('META_CAPI_ENABLED', false),
        'capi_access_token' => env('META_CAPI_ACCESS_TOKEN'),
        'graph_api_version' => env('META_GRAPH_API_VERSION', 'v25.0'),
        'capi_test_event_code' => env('META_CAPI_TEST_EVENT_CODE'),
        'capi_timeout_seconds' => env('META_CAPI_TIMEOUT_SECONDS', 5),
        'require_marketing_consent' => env('META_MARKETING_CONSENT_REQUIRED', false),
        'allow_local_pixel' => env('META_PIXEL_ALLOW_LOCALHOST', false),
    ],

];
