<?php

namespace App\Console\Commands;

use App\Events\AttendanceRecorded;
use App\Events\DeviceStatusUpdated;
use App\Models\AttendanceRecord;
use App\Models\BiometricDevice;
use App\Models\FingerprintEnrollment;
use App\Models\Schedule;
use Illuminate\Console\Command;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

class MqttListenBiometricCommand extends Command
{
    protected $signature = 'mqtt:listen-biometric';
    protected $description = 'Ecoute les capteurs biometriques et traite les empreintes';

    private ?MqttClient $mqtt = null;

    public function handle(): int
    {
        $this->info('Connexion au broker MQTT pour les capteurs biometriques...');

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
        $clientId = config('mqtt.client_id', 'core-api') . '-biometric-' . uniqid();

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

        $topic = config('mqtt.topics.biometric') . '/+/event';
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
        if (!$data) {
            $this->warn('Message invalide');
            return;
        }

        $parts = explode('/', $topic);
        $serialNumber = $parts[3] ?? null;
        $responseTopic = str_replace('/event', '/response', $topic);

        // Find device and update status
        $device = BiometricDevice::where('serial_number', $serialNumber)->first();
        if ($device) {
            $device->update([
                'is_online' => true,
                'last_sync_at' => now(),
            ]);
        }

        // Handle status action
        if (isset($data['action']) && $data['action'] === 'status') {
            $this->info("Status update du capteur {$serialNumber}");
            if ($device) {
                event(new DeviceStatusUpdated('biometric', (string) $device->id, 'online', $data));
            }
            return;
        }

        // Handle fingerprint scan
        $templateHash = $data['template_hash'] ?? null;
        if (!$templateHash) {
            $this->warn('template_hash manquant');
            $this->mqtt->publish($responseTopic, config('mqtt.response_codes.rejected'), MqttClient::QOS_AT_LEAST_ONCE);
            return;
        }

        $enrollment = FingerprintEnrollment::where('template_hash', $templateHash)
            ->where('status', 'enrolled')
            ->first();

        if (!$enrollment) {
            $this->warn("Empreinte inconnue: {$templateHash}");
            $this->mqtt->publish($responseTopic, config('mqtt.response_codes.rejected'), MqttClient::QOS_AT_LEAST_ONCE);
            return;
        }

        $employee = $enrollment->employee;
        if (!$employee || !$employee->is_active) {
            $this->warn("Employe inactif pour empreinte: {$templateHash}");
            $this->mqtt->publish($responseTopic, config('mqtt.response_codes.refused'), MqttClient::QOS_AT_LEAST_ONCE);
            return;
        }

        $today = now()->toDateString();

        // Double badge check
        $recentRecord = AttendanceRecord::where('employee_id', $employee->id)
            ->where('date', $today)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->latest()
            ->first();

        if ($recentRecord) {
            $recentRecord->update(['is_double_badge' => true]);
            $this->mqtt->publish($responseTopic, config('mqtt.response_codes.accepted'), MqttClient::QOS_AT_LEAST_ONCE);
            return;
        }

        // Entry/Exit logic
        $existingRecord = AttendanceRecord::where('employee_id', $employee->id)
            ->where('date', $today)
            ->whereNotNull('entry_time')
            ->whereNull('exit_time')
            ->latest()
            ->first();

        if ($existingRecord) {
            $exitTime = now();
            $earlyMinutes = 0;

            $schedule = Schedule::where('company_id', $employee->company_id)
                ->whereJsonContains('assigned_departments', $employee->department_id)
                ->first();

            if ($schedule) {
                $endTime = \Carbon\Carbon::parse($today . ' ' . $schedule->end_time);
                if ($exitTime->lt($endTime)) {
                    $earlyMinutes = (int) $exitTime->diffInMinutes($endTime);
                }
            }

            $existingRecord->update([
                'exit_time' => $exitTime,
                'early_departure_minutes' => $earlyMinutes,
                'status' => $earlyMinutes > 0 ? 'left_early' : $existingRecord->status,
            ]);

            event(new AttendanceRecorded($existingRecord->fresh()));
        } else {
            $entryTime = now();
            $lateMinutes = 0;
            $status = 'present';

            $schedule = Schedule::where('company_id', $employee->company_id)
                ->whereJsonContains('assigned_departments', $employee->department_id)
                ->first();

            if ($schedule) {
                $startTime = \Carbon\Carbon::parse($today . ' ' . $schedule->start_time);
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
                'source' => 'biometric',
            ]);

            event(new AttendanceRecorded($record));
        }

        $this->mqtt->publish($responseTopic, config('mqtt.response_codes.accepted'), MqttClient::QOS_AT_LEAST_ONCE);
        $this->info("Presence biometrique enregistree pour {$employee->full_name}");
    }
}
