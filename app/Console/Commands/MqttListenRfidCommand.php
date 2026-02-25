<?php

namespace App\Console\Commands;

use App\Events\AttendanceRecorded;
use App\Models\AttendanceRecord;
use App\Models\RfidCard;
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

        $host = config('mqtt.host');
        $port = (int) config('mqtt.port', 8883);
        $clientId = config('mqtt.client_id', 'core-api') . '-rfid-' . uniqid();

        $this->mqtt = new MqttClient($host, $port, $clientId, MqttClient::MQTT_3_1_1);

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

        $this->mqtt->connect($connectionSettings, true);
        $this->info('Connecte au broker MQTT.');

        $topic = config('mqtt.topics.rfid') . '/+/event';
        $this->info("Abonnement au topic: {$topic}");

        $this->mqtt->subscribe($topic, function (string $topic, string $message) {
            $this->processMessage($topic, $message);
        }, MqttClient::QOS_AT_LEAST_ONCE);

        $this->mqtt->loop(true);

        return self::SUCCESS;
    }

    private function processMessage(string $topic, string $message): void
    {
        $this->info("Message recu sur {$topic}: {$message}");

        $data = json_decode($message, true);
        if (!$data || empty($data['card_uid'])) {
            $this->warn('Message invalide: card_uid manquant');
            return;
        }

        $parts = explode('/', $topic);
        $uniqueId = $parts[3] ?? null;
        $cardUid = $data['card_uid'];

        $responseTopic = str_replace('/event', '/response', $topic);

        // Find card
        $card = RfidCard::where('uid', $cardUid)->first();

        if (!$card) {
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
        if (!$employee || !$employee->is_active) {
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
                'source' => 'rfid',
            ]);

            $this->info("Entree enregistree pour {$employee->full_name} (status: {$status})");
            event(new AttendanceRecorded($record));
        }

        $this->mqtt->publish($responseTopic, config('mqtt.response_codes.accepted'), MqttClient::QOS_AT_LEAST_ONCE);
    }
}
