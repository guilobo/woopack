<?php

return [
    'admin_password' => env('ADMIN_PASSWORD', 'admin'),
    'woocommerce' => [
        'url' => env('WOOCOMMERCE_URL'),
        'key' => env('WOOCOMMERCE_KEY'),
        'secret' => env('WOOCOMMERCE_SECRET'),
    ],
];
