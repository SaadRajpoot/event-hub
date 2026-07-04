<?php

return [
    'api_key' => env('SERVICE_API_KEY'),

    'main_api' => [
        'base_url'       => env('MAIN_API_URL', 'http://main-api'),
        'webhook_secret' => env('MAIN_API_WEBHOOK_SECRET'),
    ],
];
