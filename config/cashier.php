<?php

return [
    'key' => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
    'path' => env('CASHIER_PATH', 'stripe'),
    'webhook' => [
        'secret' => env('STRIPE_WEBHOOK_SECRET'),
        'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
        'events' => (require __DIR__.'/billing_events.php')['events'],
    ],
    'currency' => env('CASHIER_CURRENCY', 'gbp'),
    'currency_locale' => env('CASHIER_CURRENCY_LOCALE', 'en_GB'),
    'payment_notification' => env('CASHIER_PAYMENT_NOTIFICATION'),
    'logger' => env('CASHIER_LOGGER'),
];
