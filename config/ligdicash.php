<?php

return [
    'api_key' => env('LIGDICASH_API_KEY'),
    'api_secret' => env('LIGDICASH_AUTH_TOKEN'),
    'base_url' => env('LIGDICASH_BASE_URL', 'https://app.ligdicash.com/pay/v01'),
    'callback_url' => env('LIGDICASH_CALLBACK_URL'),
    'return_url' => env('LIGDICASH_RETURN_URL'),
    'cancel_url' => env('LIGDICASH_CANCEL_URL'),
    'currency' => 'XOF',
];
