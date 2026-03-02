<?php

namespace App\Console\Commands;

use App\Events\FeelbackReceived;
use App\Models\FeelbackAlert;
use App\Models\FeelbackDevice;
use App\Models\FeelbackEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

class MqttListenFeelbackCommand extends Command
{
    protected $signature = 'mqtt:listen-feelback';
    protected $description = 'Ecoute les capteurs Feelback et enregistre les retours satisfaction';

    private ?MqttClient $mqtt = null;

    public function handle(): int
    {
        $this->info('Connexion au broker MQTT pour les capteurs Feelback...');

        while (true) {
            try {
                $this->connectAndListen();
            } catch (\PhpMqtt\Client\Exceptions\DataTransferException $e) {
                $this->error("Connexion perdue: {$e->getMessage()}");
                $this->info('Reconnexion dans 5 secondes...');
                sleep(5);
            } catch (\PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException $e) {
                $this->error("Echec de connexion: {$e->getMessage()}");
                $this->info('Nouvelle tentative dans 10 secondes...');
                sleep(10);
            } catch (\Exception $e) {
                $this->error("Erreur inattendue: {$e->getMessage()}");
                $this->info('Reconnexion dans 10 secondes...');
                sleep(10);
            }
        }

        return self::SUCCESS;
    }

    private function connectAndListen(): void
    {
        $host = config('mqtt.host');
        $port = (int) config('mqtt.port', 8883);
        $clientId = config('mqtt.client_id', 'core-api') . '-feelback-' . uniqid();

        $this->mqtt = new MqttClient($host, $port, $clientId, MqttClient::MQTT_3_1_1);

        $connectionSettings = (new ConnectionSettings)
            ->setKeepAliveInterval(60)
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

        $this->mqtt->connect($connectionSettings, true);
        $this->info('Connecte au broker MQTT.');

        $topic = config('mqtt.topics.feelback') . '/+/event';
        $this->info("Abonnement au topic: {$topic}");

        $this->mqtt->subscribe($topic, function (string $topic, string $message) {
            $this->processMessage($topic, $message);
        }, MqttClient::QOS_AT_LEAST_ONCE);

        $this->mqtt->loop(true);
    }

    private function processMessage(string $topic, string $message): void
    {
        $this->info("Message recu sur {$topic}: {$message}");

        $data = json_decode($message, true);
        if (!$data || empty($data['level'])) {
            $this->warn('Message invalide: level manquant');
            return;
        }

        $level = $data['level'];
        if (!in_array($level, ['bon', 'neutre', 'mauvais'])) {
            $this->warn("Niveau invalide: {$level}");
            return;
        }

        $parts = explode('/', $topic);
        $serialNumber = $parts[3] ?? null;
        $responseTopic = str_replace('/event', '/response', $topic);

        // Find device
        $device = FeelbackDevice::where('serial_number', $serialNumber)->first();
        if (!$device) {
            $this->warn("Capteur Feelback inconnu: {$serialNumber}");
            return;
        }

        // Update device status
        $device->update([
            'is_online' => true,
            'last_ping_at' => now(),
        ]);

        // Create feelback entry
        $entry = FeelbackEntry::create([
            'device_id' => $device->id,
            'level' => $level,
            'site_id' => $device->site_id,
        ]);

        $this->info("Feelback enregistre: {$level} depuis {$serialNumber}");

        // Check alert thresholds
        $threshold = (int) Cache::get('feelback_alert_threshold', 5);
        $mauvaisCount = FeelbackEntry::where('device_id', $device->id)
            ->where('level', 'mauvais')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($mauvaisCount >= $threshold) {
            $existingAlert = FeelbackAlert::where('device_id', $device->id)
                ->where('type', 'threshold_exceeded')
                ->where('created_at', '>=', now()->subHour())
                ->first();

            if (!$existingAlert) {
                FeelbackAlert::create([
                    'device_id' => $device->id,
                    'site_id' => $device->site_id,
                    'type' => 'threshold_exceeded',
                    'message' => "Seuil d'insatisfaction depasse: {$mauvaisCount} avis negatifs en 1h",
                    'threshold' => $threshold,
                    'current_value' => $mauvaisCount,
                ]);
                $this->warn("Alerte seuil: {$mauvaisCount} avis mauvais en 1h");
            }
        }

        // Publish confirmation
        $this->mqtt->publish($responseTopic, '0x00ACK01', MqttClient::QOS_AT_LEAST_ONCE);

        // Broadcast event
        event(new FeelbackReceived($entry));
    }
}
