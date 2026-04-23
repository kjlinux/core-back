<?php

return [
    'host' => env('MQTT_HOST', 'localhost'),
    'port' => (int) env('MQTT_PORT', 1883),
    'client_id' => env('MQTT_CLIENT_ID', 'core-api'),
    'tls_enabled' => filter_var(env('MQTT_TLS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'tls_ca_file' => env('MQTT_TLS_CA_FILE'),
    'auth' => [
        'enabled' => filter_var(env('MQTT_AUTH_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'username' => env('MQTT_AUTH_USERNAME'),
        'password' => env('MQTT_AUTH_PASSWORD'),
    ],
    'topics' => [
        'rfid' => 'core/rfid/sensor',
        'biometric' => 'core/biometric/sensor',
        'feelback' => 'core/feelback/sensor',
    ],
    'response_codes' => [
        'accepted' => '0x001020',
        'refused' => '0x003020',
        'rejected' => '0x108080',
    ],
    'command_codes' => [
        'rfid' => [
            'RESET' => '0x108070',
            'REBOOT' => '0x108090',
            'WAKE_UP' => '0x1080A0',
            'SLEEP' => '0x1080B0',
            'STATUS' => '0x100010',
            'SCAN' => '0x100030',
        ],
        'biometric' => [
            'RESET' => '0x108070',
            'REBOOT' => '0x108090',
            'WAKE_UP' => '0x1080A0',
            'SLEEP' => '0x1080B0',
            'STATUS' => '0x100010',
            'ENROLE' => '0x100200',
            'DELETE' => '0x2000B0',
            'DELETE_ALL' => '0x2000C0',
        ],
    ],
];
