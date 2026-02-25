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
        'accepted' => '0x001021J',
        'refused' => '0x0030212',
        'rejected' => '0x1080814',
    ],
    'command_codes' => [
        'REBOOT' => '0x108091S',
        'RESET' => '0x1080713',
        'STATUS' => '0x1000119',
        'RESTART' => '0x108091R',
    ],
];
