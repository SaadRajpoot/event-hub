<?php

return [

    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'   => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'payment_service' => [
        'base_url'       => env('PAYMENT_SERVICE_URL', 'http://payment-service'),
        'api_key'        => env('PAYMENT_SERVICE_API_KEY'),
        'webhook_secret' => env('PAYMENT_SERVICE_WEBHOOK_SECRET'),
    ],

    'notification_service' => [
        'base_url' => env('NOTIFICATION_SERVICE_URL', 'http://notification-service'),
        'api_key'  => env('NOTIFICATION_SERVICE_API_KEY'),
    ],

];
