<?php

return [
    'api_key' => env('SERVICE_API_KEY'),

    'mail' => [
        'from_address' => env('MAIL_FROM_ADDRESS', 'noreply@eventhub.local'),
        'from_name'    => env('MAIL_FROM_NAME', 'EventHub'),
    ],
];
