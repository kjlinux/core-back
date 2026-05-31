<?php

return [
    'base_url' => env('INTOUCH_BASE_URL', 'https://api.intouchgroup.net'),
    'login_agent' => env('INTOUCH_LOGIN_AGENT'),
    'password_agent' => env('INTOUCH_PASSWORD_AGENT'),
    'api_key' => env('INTOUCH_API_KEY'),
    'partner_id' => env('INTOUCH_PARTNER_ID'),
    'service_code' => env('INTOUCH_SERVICE_CODE', 'CASHIN'),
    'webhook_secret' => env('INTOUCH_WEBHOOK_SECRET'),
    'callback_url' => env('INTOUCH_CALLBACK_URL')
        ?: rtrim((string) env('APP_URL', ''), '/').'/api/payment/intouch/callback',
    'return_url' => env('INTOUCH_RETURN_URL')
        ?: rtrim((string) env('APP_FRONTEND_URL', ''), '/').'/marketplace/payment/callback',
    'cancel_url' => env('INTOUCH_CANCEL_URL')
        ?: rtrim((string) env('APP_FRONTEND_URL', ''), '/').'/marketplace/payment/callback?status=cancelled',
    'currency' => 'XOF',
];
