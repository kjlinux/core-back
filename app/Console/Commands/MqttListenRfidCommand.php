<?php

namespace App\Console\Commands;

use App\Events\AttendanceRecorded;
use App\Events\DeviceStatusUpdated;
use App\Models\AttendanceRecord;
use App\Models\RfidCard;
use App\Models\RfidDevice;
use App\Models\Schedule;
use Illuminate\Console\Command;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

class MqttListenRfidCommand extends Command
{
    protected $signature = 'mqtt:listen-rfid';

    protected $description = 'Ecoute les capteurs RFID et traite les badges scannes';

    private ?MqttClient $mqtt = null;

    public function handle(): int
    {
        $this->info('Connexion au broker MQTT pour les capteurs RFID...');

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
        $clientId = config('mqtt.client_id', 'core-api').'-rfid-'.uniqid();

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

        $topic = config('mqtt.topics.rfid').'/+/event';
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
        if (! $data) {
            $this->warn('Message invalide: JSON malformé');

            return;
        }

        // Ignorer les messages de statut purs
        if (isset($data['status']) && ! isset($data['card_uid']) && ! isset($data['uid'])) {
            $this->info('Message de statut ignoré');

            return;
        }

        // Support card_uid (format cible ESP) et uid (format legacy)
        $rawUid = $data['card_uid'] ?? $data['uid'] ?? null;

        if (empty($rawUid)) {
            $this->warn('Message invalide: card_uid manquant');

            return;
        }

        // Ignorer les messages sync/heartbeat qui ont uid mais pas de scan réel
        if (! isset($data['card_uid']) && isset($data['timestamp'])) {
            $this->info("Message sync ignoré (uid={$rawUid})");

            return;
        }

        $cardUid = $this->normalizeUid($rawUid);
        $this->info("UID normalisé: {$rawUid} → {$cardUid}");

        $parts = explode('/', $topic);
        $uniqueId = $parts[3] ?? null;

        $device = RfidDevice::where('serial_number', $uniqueId)->first();
        if ($device) {
            $device->update(['is_online' => true, 'last_ping_at' => now()]);
            event(new DeviceStatusUpdated('rfid', (string) $device->id, 'online', $data));
        }

        $responseTopic = str_replace('/event', '/response', $topic);

        $card = RfidCard::where('uid', $cardUid)->first();

        if (! $card) {
            $this->warn("Carte inconnue: {$cardUid}");
            $this->mqtt->publish($responseTopic, config('mqtt.response_codes.rejected'), MqttClient::QOS_AT_LEAST_ONCE);

            return;
        }

        if ($card->status !== 'active') {
            $this->warn("Carte inactive/bloquee: {$cardUid}");
            $this->mqtt->publish($responseTopic, config('mqtt.response_codes.refused'), MqttClient::QOS_AT_LEAST_ONCE);

            return;
        }

        $employee = $card->employee;
        if (! $employee || ! $employee->is_active) {
            $this->warn("Employe inactif ou non assigne pour carte: {$cardUid}");
            $this->mqtt->publish($responseTopic, config('mqtt.response_codes.refused'), MqttClient::QOS_AT_LEAST_ONCE);

            return;
        }

        $today = now()->toDateString();

        // Double badge check (5 min window)
        $recentRecord = AttendanceRecord::where('employee_id', $employee->id)
            ->where('date', $today)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->latest()
            ->first();

        if ($recentRecord) {
            $this->info("Double badge detecte pour {$employee->full_name}");
            $recentRecord->update(['is_double_badge' => true]);
            $this->mqtt->publish($responseTopic, config('mqtt.response_codes.accepted'), MqttClient::QOS_AT_LEAST_ONCE);

            return;
        }

        // Check existing record today for entry/exit logic
        $existingRecord = AttendanceRecord::where('employee_id', $employee->id)
            ->where('date', $today)
            ->whereNotNull('entry_time')
            ->whereNull('exit_time')
            ->latest()
            ->first();

        if ($existingRecord) {
            // Exit badge
            $exitTime = now();
            $earlyMinutes = 0;

            $schedule = Schedule::where('company_id', $employee->company_id)
                ->whereJsonContains('assigned_departments', $employee->department_id)
                ->first();

            if ($schedule) {
                $endTime = \Carbon\Carbon::parse($today.' '.$schedule->end_time);
                if ($exitTime->lt($endTime)) {
                    $earlyMinutes = (int) $exitTime->diffInMinutes($endTime);
                }
            }

            $existingRecord->update([
                'exit_time' => $exitTime,
                'early_departure_minutes' => $earlyMinutes,
                'status' => $earlyMinutes > 0 ? 'left_early' : $existingRecord->status,
            ]);

            $this->info("Sortie enregistree pour {$employee->full_name}");
            event(new AttendanceRecorded($existingRecord->fresh()));
        } else {
            // Entry badge
            $entryTime = now();
            $lateMinutes = 0;
            $status = 'present';

            $schedule = Schedule::where('company_id', $employee->company_id)
                ->whereJsonContains('assigned_departments', $employee->department_id)
                ->first();

            if ($schedule) {
                $startTime = \Carbon\Carbon::parse($today.' '.$schedule->start_time);
                $tolerance = $schedule->late_tolerance ?? 0;
                if ($entryTime->gt($startTime->addMinutes($tolerance))) {
                    $lateMinutes = (int) $entryTime->diffInMinutes($startTime);
                    $status = 'late';
                }
            }

            $record = AttendanceRecord::create([
                'employee_id' => $employee->id,
                'date' => $today,
                'entry_time' => $entryTime,
                'status' => $status,
                'late_minutes' => $lateMinutes,
                'source' => 'rfid',
            ]);

            $this->info("Entree enregistree pour {$employee->full_name} (status: {$status})");
            event(new AttendanceRecorded($record));
        }

        $this->mqtt->publish($responseTopic, config('mqtt.response_codes.accepted'), MqttClient::QOS_AT_LEAST_ONCE);
    }

    /**
     * Normalise un UID RFID au format stocké en base : "1A:7B:91:AE"
     * Accepte : "1A7B91AE", "1a:7b:91:ae", "1A-7B-91-AE", etc.
     */
    private function normalizeUid(string $rawUid): string
    {
        // Retirer tous les séparateurs existants
        $clean = strtoupper(str_replace([':', '-', ' '], '', $rawUid));

        // Réinsérer les deux-points toutes les 2 lettres : AABBCCDD → AA:BB:CC:DD
        return implode(':', str_split($clean, 2));
    }
}
