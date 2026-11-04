<?php

/*
 | Gateway credentials live ONLY in .env (never in the database, never in the browser).
 | The Payment Method page shows whether each value is configured, never the value.
 */
return [
    'currency' => 'BDT',

    'shurjopay' => [
        'username' => env('SHURJOPAY_USERNAME'),
        'password' => env('SHURJOPAY_PASSWORD'),
        'prefix' => env('SHURJOPAY_PREFIX'),
        'mode' => env('SHURJOPAY_MODE', 'sandbox'),   // sandbox | live - live must be chosen on purpose
        'sandbox_url' => 'https://sandbox.shurjopayment.com/api',
        'live_url' => 'https://engine.shurjopayment.com/api',
        'customer_city' => env('SHURJOPAY_CUSTOMER_CITY', 'Dhaka'),
        'customer_post_code' => env('SHURJOPAY_CUSTOMER_POST_CODE', '1000'),
        'timeout' => 20,
    ],
];
