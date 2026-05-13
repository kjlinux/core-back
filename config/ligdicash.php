<?php

return [
    'api_key' => env('LIGDICASH_API_KEY'),
    'api_secret' => env('LIGDICASH_AUTH_TOKEN'),
    'base_url' => env('LIGDICASH_BASE_URL', 'https://app.ligdicash.com/pay/v01'),
    'callback_url' => env('LIGDICASH_CALLBACK_URL'),
    'return_url' => env('LIGDICASH_RETURN_URL')
        ?: rtrim((string) env('APP_FRONTEND_URL', ''), '/') . '/marketplace/orders',
    'cancel_url' => env('LIGDICASH_CANCEL_URL')
        ?: rtrim((string) env('APP_FRONTEND_URL', ''), '/') . '/marketplace/checkout',
    'currency' => 'XOF',
];
