<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Platform Commission Rate
    |--------------------------------------------------------------------------
    | Decimal fraction (e.g. 0.10 = 10%). Can be overridden per vendor.
    */
    'commission_rate' => env('PLATFORM_COMMISSION_RATE', 0.10),

    /*
    |--------------------------------------------------------------------------
    | Order Expiry (minutes)
    |--------------------------------------------------------------------------
    */
    'order_expiry_minutes' => env('ORDER_EXPIRY_MINUTES', 15),
];
