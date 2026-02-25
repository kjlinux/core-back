<?php

namespace App\Services;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

class MqttService
{
    public function createClient(?string $clientId = null): MqttClient
    {
        $host = config('mqtt.host');
        $port = (int) config('mqtt.port', 8883);
        $clientId = $clientId ?? config('mqtt.client_id') . '-' . uniqid();

        $mqtt = new MqttClient($host, $port, $clientId, MqttClient::MQTT_3_1_1);

        $connectionSettings = (new ConnectionSettings)
            ->setKeepAliveInterval(10)
            ->setConnectTimeout(30);

        if (config('mqtt.auth.enabled')) {
            $connectionSettings = $connectionSettings
                ->setUsername(config('mqtt.auth.username'))
                ->setPassword(config('mqtt.auth.password'));
        }

        if (config('mqtt.tls_enabled')) {
            $connectionSettings = $connectionSettings
                ->setUseTls(true)
                ->setTlsSelfSignedAllowed(true)
                ->setTlsVerifyPeer(false)
                ->setTlsVerifyPeerName(false);

            if ($caFile = config('mqtt.tls_ca_file')) {
                $connectionSettings = $connectionSettings
                    ->setTlsCertificateAuthorityFile($caFile);
            }
        }

        $mqtt->connect($connectionSettings, true);

        return $mqtt;
    }

    public function publish(string $topic, string $message, int $qos = MqttClient::QOS_AT_LEAST_ONCE): void
    {
        $mqtt = $this->createClient();
        $mqtt->publish($topic, $message, $qos);
        $mqtt->disconnect();
    }

    public function getResponseTopic(string $eventTopic): string
    {
        return str_replace('/event', '/response', $eventTopic);
    }

    public function extractUniqueId(string $topic): ?string
    {
        $parts = explode('/', $topic);
        return $parts[3] ?? null;
    }
}
