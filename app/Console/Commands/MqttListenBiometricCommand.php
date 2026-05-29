<?php

namespace App\Console\Commands;

use App\Events\AttendanceRecorded;
use App\Events\DeviceStatusUpdated;
use App\Models\AttendanceRecord;
use App\Models\BiometricAuditLog;
use App\Models\BiometricDevice;
use App\Models\Employee;
use App\Models\FingerprintEnrollment;
use App\Models\OtaUpdateLog;
use App\Services\ScheduleResolverService;
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
        $clientId = config('mqtt.client_id', 'core-api').'-biometric-'.uniqid();

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

        $topic = config('mqtt.topics.biometric').'/+/event';
        $this->info("Abonnement au topic: {$topic}");

        $this->mqtt->subscribe($topic, function (string $topic, string $message) {
            $this->processMessage($topic, $message);
        }, MqttClient::QOS_AT_LEAST_ONCE);

        $this->mqtt->registerLoopEventHandler(function () {
            static $lastHeartbeat = 0;
            $now = time();
            if ($now - $lastHeartbeat >= 30) {
                app(\App\Services\HealthService::class)->recordListenerHeartbeat('biometric');
                $lastHeartbeat = $now;
            }
        });

        $this->mqtt->loop(true);
    }

    private function processMessage(string $topic, string $message): void
    {
        $this->info("Message recu sur {$topic}: {$message}");

        $data = json_decode($message, true);
        if (! $data) {
            $this->warn('Message invalide');

            return;
        }

        $parts = explode('/', $topic);
        $serialNumber = $parts[3] ?? null;
        $responseTopic = str_replace('/event', '/response', $topic);

        $device = BiometricDevice::where('serial_number', $serialNumber)->first();

        // Reponse OTA traitee en premier : l'ESP32 publie
        // {event:"ota_result",log_id,success,version,error}. On evite ainsi de
        // re-pousser via le retry la version qui vient justement de se terminer.
        if (($data['event'] ?? null) === 'ota_result') {
            $this->processOtaResponse($data, $serialNumber);

            return;
        }

        // Mark online et relancer une OTA en attente si le terminal se reconnecte
        if ($device) {
            $this->markOnlineAndRetryOta($device, $data);
        }

        // Handle status action (device deja marque online + retry OTA ci-dessus)
        if (isset($data['action']) && $data['action'] === 'status') {
            $this->info("Status update du capteur {$serialNumber}");
            if ($device) {
                event(new DeviceStatusUpdated('biometric', (string) $device->id, 'online', $data));
            }

            return;
        }

        // Handle enrollment response from device
        if (isset($data['action']) && $data['action'] === 'enrollment_result') {
            $this->processEnrollmentResult($data, $responseTopic);

            return;
        }

        // Handle delete responses from device
        if (isset($data['action']) && in_array($data['action'], ['delete_all_result', 'delete_result'], true)) {
            $this->info("Reponse {$data['action']} du capteur {$serialNumber}: ".json_encode($data));

            return;
        }

        // Handle fingerprint scan
        $templateHash = $data['template_hash'] ?? null;
        if (! $templateHash) {
            $this->warn('template_hash manquant');
            $this->mqtt->publish($responseTopic, config('mqtt.response_codes.rejected'), MqttClient::QOS_AT_LEAST_ONCE);

            return;
        }

        // Scoper par device : un meme template_hash (FP0001) peut exister sur
        // plusieurs terminaux. Sans ce scope, first() retourne le mauvais employe.
        $enrollment = FingerprintEnrollment::where('template_hash', $templateHash)
            ->when($device, fn ($q) => $q->where('device_id', $device->id))
            ->where('status', 'enrolled')
            ->first();

        if (! $enrollment) {
            $this->warn("Empreinte inconnue: {$templateHash}");
            $this->mqtt->publish($responseTopic, config('mqtt.response_codes.rejected'), MqttClient::QOS_AT_LEAST_ONCE);

            return;
        }

        $employee = $enrollment->employee;
        if (! $employee || ! $employee->is_active) {
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

        $resolver = app(ScheduleResolverService::class);

        if ($existingRecord) {
            $exitTime = now();
            $earlyMinutes = 0;

            $schedule = $resolver->resolveForEmployee($employee->company_id, $employee->department_id, $exitTime);

            if ($schedule) {
                $earlyMinutes = $resolver->calculateEarlyDepartureMinutes($schedule, $exitTime);
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

            $schedule = $resolver->resolveForEmployee($employee->company_id, $employee->department_id, $entryTime);

            if ($schedule) {
                $lateMinutes = $resolver->calculateLateMinutes($schedule, $entryTime);
                $status = $lateMinutes > 0 ? 'late' : 'present';
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

    private function processEnrollmentResult(array $data, string $responseTopic): void
    {
        $enrollmentId = $data['enrollment_id'] ?? null;
        $templateHash = $data['template_hash'] ?? null;
        $success = $data['success'] ?? false;

        if (! $enrollmentId) {
            $this->warn('enrollment_id manquant dans enrollment_result');

            return;
        }

        $enrollment = FingerprintEnrollment::find($enrollmentId);
        if (! $enrollment) {
            $this->warn("Enrollment introuvable: {$enrollmentId}");

            return;
        }

        if ($success && $templateHash) {
            // Le firmware renvoie le template_hash qu'il a reellement stocke.
            // Normalement identique a celui pre-reserve par le backend. En cas
            // d'ecart, on tente la mise a jour -- si elle viole l'index unique
            // (collision avec un autre enrollment sur le meme device), on marque
            // en failed et on alerte.
            try {
                $enrollment->update([
                    'status' => 'enrolled',
                    'template_hash' => $templateHash,
                    'enrolled_at' => now(),
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // 23000 = MySQL integrity constraint / 23505 = PostgreSQL unique violation.
                if (in_array($e->getCode(), ['23000', '23505'], true)) {
                    $this->warn("Collision template_hash={$templateHash} sur device={$enrollment->device_id} -- enrollment {$enrollmentId} passe en failed");
                    $enrollment->update(['status' => 'failed']);
                    $this->mqtt->publish($responseTopic, config('mqtt.response_codes.rejected'), MqttClient::QOS_AT_LEAST_ONCE);

                    return;
                }
                throw $e;
            }

            $employee = Employee::find($enrollment->employee_id);
            if ($employee) {
                $employee->update(['biometric_enrolled' => true]);

                if ($enrollment->device) {
                    $enrollment->device->increment('enrolled_count');
                }
            }

            BiometricAuditLog::create([
                'user_id' => $enrollment->employee_id,
                'user_name' => $employee ? $employee->full_name : $enrollment->employee_id,
                'action' => 'enrollment_completed',
                'target' => $employee ? $employee->full_name : $enrollment->employee_id,
                'details' => 'Enrolement biometrique reussi - template_hash: '.substr($templateHash, 0, 12).'...',
            ]);

            $this->mqtt->publish($responseTopic, config('mqtt.response_codes.accepted'), MqttClient::QOS_AT_LEAST_ONCE);
            $this->info("Enrolement reussi pour enrollment {$enrollmentId}");
        } else {
            $enrollment->update(['status' => 'failed']);

            $failedEmployee = $enrollment->employee ?? Employee::find($enrollment->employee_id);

            BiometricAuditLog::create([
                'user_id' => $enrollment->employee_id,
                'user_name' => $failedEmployee ? $failedEmployee->full_name : (string) $enrollment->employee_id,
                'action' => 'enrollment_failed',
                'target' => $failedEmployee ? $failedEmployee->full_name : (string) $enrollment->employee_id,
                'details' => 'Enrolement biometrique echoue - '.($data['error'] ?? 'Erreur inconnue'),
            ]);

            $this->mqtt->publish($responseTopic, config('mqtt.response_codes.rejected'), MqttClient::QOS_AT_LEAST_ONCE);
            $this->warn("Enrolement echoue pour enrollment {$enrollmentId}");
        }
    }

    private function processOtaResponse(array $data, ?string $serial): void
    {
        $this->info('Reponse OTA biometrique recue: '.json_encode($data));

        $logId = $data['log_id'] ?? null;
        $success = (bool) ($data['success'] ?? false);
        $version = $data['version'] ?? null;
        $errorMsg = $data['error'] ?? null;

        // Mettre à jour le log OTA
        if ($logId) {
            $log = OtaUpdateLog::find($logId);
            if ($log) {
                $log->update([
                    'status' => $success ? 'success' : 'failed',
                    'completed_at' => now(),
                    'error_message' => $success ? null : ($errorMsg ?? 'Echec signale par le terminal'),
                ]);
                $this->info("OtaUpdateLog {$logId} mis a jour : ".($success ? 'success' : 'failed'));
            } else {
                $this->warn("OtaUpdateLog introuvable: {$logId}");
            }
        }

        // Mettre à jour la version firmware du terminal biométrique
        if ($success && $version && $serial) {
            BiometricDevice::where('serial_number', $serial)
                ->update(['firmware_version' => $version]);
            $this->info("Firmware biometrique {$serial} mis a jour vers {$version}");
        }
    }

    /**
     * Marque le terminal en ligne et, s'il vient de se reconnecter, relance
     * toute mise a jour OTA restee en attente (terminal offline au moment du push).
     */
    private function markOnlineAndRetryOta(BiometricDevice $device, array $data): void
    {
        $wasOffline = ! $device->is_online;

        $updateData = ['is_online' => true, 'last_sync_at' => now()];
        if (! empty($data['firmware_version'])) {
            $updateData['firmware_version'] = $data['firmware_version'];
        }
        $device->update($updateData);

        if ($wasOffline) {
            $this->retryPendingOtaUpdates($device);
        }
    }

    /**
     * Republie l'ordre OTA pour les logs encore en attente de ce terminal.
     * Le push MQTT initial (QoS 0) est perdu si le terminal etait offline ;
     * on rejoue donc l'ordre des qu'il se reconnecte. Inclut les logs passes
     * en "failed" par timeout (offline) mais pas les vrais echecs de flash.
     */
    private function retryPendingOtaUpdates(BiometricDevice $device): void
    {
        if (empty($device->serial_number) || ! $this->mqtt) {
            return;
        }

        $currentVersion = $device->firmware_version;

        $logs = OtaUpdateLog::with('firmwareVersion')
            ->where('device_id', $device->id)
            ->where('device_kind', 'biometric')
            ->where('started_at', '<', now()->subSeconds(30))
            ->where(function ($q) {
                $q->whereIn('status', ['pending', 'in_progress'])
                  ->orWhere(function ($q2) {
                      $q2->where('status', 'failed')
                         ->where('error_message', 'like', 'Timeout%');
                  });
            })
            ->orderByDesc('started_at')
            ->get()
            ->unique('firmware_version_id');

        foreach ($logs as $log) {
            $fw = $log->firmwareVersion;
            if (! $fw || ! $fw->file_path) {
                continue;
            }

            if ($currentVersion && $fw->version === $currentVersion) {
                $log->update(['status' => 'success', 'completed_at' => now()]);
                continue;
            }

            $fileUrl = rtrim(config('app.url'), '/').'/storage/'.$fw->file_path;
            $topic = config('mqtt.topics.biometric').'/'.$device->serial_number.'/response';

            try {
                $this->mqtt->publish($topic, json_encode([
                    'cmd' => '0x1080D0',
                    'url' => $fileUrl,
                    'version' => $fw->version,
                    'log_id' => (string) $log->id,
                ]), MqttClient::QOS_AT_LEAST_ONCE);
                $log->update(['status' => 'in_progress']);
                $this->info("[OTA retry] {$device->serial_number} -> {$fw->version}");
            } catch (\Exception $e) {
                $this->warn("[OTA retry] echec {$device->serial_number}: {$e->getMessage()}");
            }
        }
    }
}
