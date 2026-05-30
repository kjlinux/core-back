<?php

return [
    'api_key' => env('LIGDICASH_API_KEY'),
    'api_secret' => env('LIGDICASH_AUTH_TOKEN'),
    'base_url' => env('LIGDICASH_BASE_URL', 'https://app.ligdicash.com/pay/v01'),
    'callback_url' => env('LIGDICASH_CALLBACK_URL')
        ?: rtrim((string) env('APP_URL', ''), '/').'/api/payment/callback',
    'return_url' => env('LIGDICASH_RETURN_URL')
        ?: rtrim((string) env('APP_FRONTEND_URL', ''), '/').'/marketplace/orders',
    'cancel_url' => env('LIGDICASH_CANCEL_URL')
        ?: rtrim((string) env('APP_FRONTEND_URL', ''), '/').'/marketplace/checkout',

    // URLs de redirection specifiques au flux d'abonnement : retour sur la page
    // « Mon abonnement », annulation sur la selection de plan.
    'subscription_return_url' => env('LIGDICASH_SUBSCRIPTION_RETURN_URL')
        ?: rtrim((string) env('APP_FRONTEND_URL', ''), '/').'/abonnement',
    'subscription_cancel_url' => env('LIGDICASH_SUBSCRIPTION_CANCEL_URL')
        ?: rtrim((string) env('APP_FRONTEND_URL', ''), '/').'/abonnement/plans',

    'currency' => 'XOF',
];
