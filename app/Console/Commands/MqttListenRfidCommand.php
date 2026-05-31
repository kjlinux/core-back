<?php

namespace App\Console\Commands;

use App\Events\AttendanceRecorded;
use App\Events\CardScanned;
use App\Events\DeviceStatusUpdated;
use App\Models\AttendanceRecord;
use App\Models\DeviceAlert;
use App\Models\DeviceLog;
use App\Models\OtaUpdateLog;
use App\Models\RfidCard;
use App\Models\RfidDevice;
use App\Services\AlertService;
use App\Services\ScheduleResolverService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
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

        // Heartbeat pour le HealthService (toutes les 30s via la boucle MQTT)
        $this->mqtt->registerLoopEventHandler(function () {
            static $lastHeartbeat = 0;
            $now = time();
            if ($now - $lastHeartbeat >= 30) {
                app(\App\Services\HealthService::class)->recordListenerHeartbeat('rfid');
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
            $this->warn('Message invalide: JSON malformé');

            return;
        }

        // Last Will MQTT : le broker publie {"status":"offline"} sur .../event des que le
        // terminal se deconnecte (coupure secteur/reseau). Detection quasi instantanee, sans
        // attendre le seuil de health:check-devices. A traiter AVANT la branche heartbeat
        // (sans card_uid) qui sinon le prendrait pour un signe de vie et le remettrait en ligne.
        if (($data['status'] ?? null) === 'offline') {
            $this->info('Last Will recu (terminal hors ligne)');
            $parts = explode('/', $topic);
            $serial = $parts[3] ?? null;
            if ($serial && ($device = RfidDevice::where('serial_number', $serial)->first())) {
                $this->markOfflineFromLwt($device);
            }

            return;
        }

        // Reponse OTA : l'ESP32 publie {event:"ota_result",log_id,success,version,error}
        // sur core/rfid/sensor/{serial}/event apres flash.
        if (($data['event'] ?? null) === 'ota_result') {
            $parts = explode('/', $topic);
            $serial = $parts[3] ?? null;
            $this->processOtaResponse($data, $serial);

            return;
        }

        // Log applicatif distant : le firmware publie {event:"log",level,message,uptime,version}
        // sur core/rfid/sensor/{serial}/event pour les niveaux warning/error/critical. A traiter
        // AVANT la branche heartbeat (sans card_uid) qui sinon le prendrait pour un signe de vie.
        if (($data['event'] ?? null) === 'log') {
            $parts = explode('/', $topic);
            $serial = $parts[3] ?? null;
            $this->processDeviceLog($data, $serial);

            return;
        }

        // Messages sans badge : statut de connexion ({"status":"online"}) OU reponse
        // a la commande STATUS du terminal ({"device_id","version","uptime","ip",...}).
        // Aucun pointage, mais on marque le terminal en ligne, on rafraichit
        // last_ping_at et on relance une OTA en attente s'il vient de se reconnecter.
        if (! isset($data['card_uid']) && ! isset($data['uid'])) {
            $this->info('Message de statut/heartbeat terminal');
            $parts = explode('/', $topic);
            $serial = $parts[3] ?? null;
            if ($serial && ($device = RfidDevice::where('serial_number', $serial)->first())) {
                // Le firmware reporte sa version sous "version" ; markOnlineAndRetryOta
                // attend "firmware_version".
                if (! empty($data['version']) && empty($data['firmware_version'])) {
                    $data['firmware_version'] = $data['version'];
                }
                $this->markOnlineAndRetryOta($device, $data);
            }

            return;
        }

        // Support card_uid (format cible ESP) et uid (format legacy)
        $rawUid = $data['card_uid'] ?? $data['uid'] ?? null;

        if (empty($rawUid)) {
            $this->warn('Message invalide: card_uid manquant');

            return;
        }

        // Messages sync/heartbeat : uid present mais pas de scan reel. On ne cree
        // pas de pointage, mais on rafraichit last_ping_at pour refleter que le
        // terminal est bien vivant entre deux badges.
        if (! isset($data['card_uid']) && isset($data['timestamp'])) {
            $this->info("Message sync (uid={$rawUid})");
            $parts = explode('/', $topic);
            $serial = $parts[3] ?? null;
            if ($serial && ($device = RfidDevice::where('serial_number', $serial)->first())) {
                $this->markOnlineAndRetryOta($device, $data);
            }

            return;
        }

        $cardUid = strtoupper($rawUid);

        $parts = explode('/', $topic);
        $uniqueId = $parts[3] ?? null;

        $device = RfidDevice::where('serial_number', $uniqueId)->first();

        // Commande SCAN : le terminal renvoie le UID pour enregistrement d'une nouvelle carte
        // Le payload contient type="scan" — on broadcast le UID sans créer de pointage
        if (($data['type'] ?? null) === 'scan') {
            $this->info("Scan d'enregistrement recu: UID={$cardUid}");
            if ($device) {
                event(new CardScanned($cardUid, (string) $device->id, (string) $device->company_id));
            }

            return;
        }
        if ($device) {
            $this->markOnlineAndRetryOta($device, $data);
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

        // Cloisonnement multi-tenant : la carte/employe doit appartenir a la MEME entreprise
        // que le terminal. Une carte mal assignee (ou un message MQTT usurpe) ne doit jamais
        // creer ni alterer le pointage d'une autre entreprise.
        if ($device && (string) $employee->company_id !== (string) $device->company_id) {
            $this->warn("Carte {$cardUid} rattachee a une autre entreprise que le terminal {$uniqueId} — refuse");
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

        $resolver = app(ScheduleResolverService::class);

        if ($existingRecord) {
            // Exit badge
            $exitTime = now();
            $earlyMinutes = 0;

            $schedule = $resolver->resolveForEmployee($employee->company_id, $employee->department_id, $exitTime, $employee->schedule_id);

            if ($schedule) {
                $earlyMinutes = $resolver->calculateEarlyDepartureMinutes($schedule, $exitTime);
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

            $schedule = $resolver->resolveForEmployee($employee->company_id, $employee->department_id, $entryTime, $employee->schedule_id);

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
                'source' => 'rfid',
            ]);

            $this->info("Entree enregistree pour {$employee->full_name} (status: {$status})");
            event(new AttendanceRecorded($record));
        }

        $this->mqtt->publish($responseTopic, config('mqtt.response_codes.accepted'), MqttClient::QOS_AT_LEAST_ONCE);
    }

    private function processOtaResponse(array $data, ?string $serial): void
    {
        $this->info('Reponse OTA RFID recue: '.json_encode($data));

        $logId = $data['log_id'] ?? null;
        $success = (bool) ($data['success'] ?? false);
        $version = $data['version'] ?? null;
        $errorMsg = $data['error'] ?? null;

        // Mettre à jour le log OTA — uniquement s'il appartient bien au terminal (RFID) qui
        // a publie le message. Sans cette verification, un message usurpe avec un log_id d'une
        // autre entreprise pourrait corrompre son suivi de mise a jour firmware.
        if ($logId) {
            $device = $serial ? RfidDevice::where('serial_number', $serial)->first() : null;

            $log = $device
                ? OtaUpdateLog::where('device_id', $device->id)
                    ->where('device_kind', 'rfid')
                    ->find($logId)
                : null;

            if ($log) {
                $log->update([
                    'status' => $success ? 'success' : 'failed',
                    'completed_at' => now(),
                    'error_message' => $success ? null : ($errorMsg ?? 'Echec signale par le terminal'),
                ]);
                $this->info("OtaUpdateLog {$logId} mis a jour : ".($success ? 'success' : 'failed'));
            } else {
                $this->warn("OtaUpdateLog {$logId} introuvable ou n'appartient pas au terminal {$serial}");
            }
        }

        // Mettre à jour la version firmware du terminal
        if ($success && $version && $serial) {
            RfidDevice::where('serial_number', $serial)
                ->update(['firmware_version' => $version]);
            $this->info("Firmware RFID {$serial} mis a jour vers {$version}");
        }
    }

    /**
     * Persiste un log applicatif remonte par le terminal (warning/error/critical).
     * Aucune action sur le statut en ligne du terminal : un message d'erreur n'est pas
     * un heartbeat. Les logs alimentent le digest quotidien (device-logs:send-digest).
     *
     * @param  array<string, mixed>  $data
     */
    private function processDeviceLog(array $data, ?string $serial): void
    {
        $message = trim((string) ($data['message'] ?? ''));
        if ($message === '') {
            $this->warn('Log terminal ignore : message vide');

            return;
        }

        $allowedLevels = ['debug', 'info', 'warning', 'error', 'critical'];
        $level = strtolower((string) ($data['level'] ?? 'info'));
        if (! in_array($level, $allowedLevels, true)) {
            $level = 'info';
        }

        $device = $serial ? RfidDevice::where('serial_number', $serial)->first() : null;

        $context = array_filter([
            'uptime' => $data['uptime'] ?? null,
            'ip' => $data['ip'] ?? null,
        ], static fn ($value) => $value !== null);

        DeviceLog::create([
            'company_id' => $device?->company_id,
            'site_id' => $device?->site_id,
            'device_id' => $device?->id,
            'device_kind' => 'rfid',
            'serial_number' => $serial,
            'level' => $level,
            'message' => mb_substr($message, 0, 1000),
            'firmware_version' => $data['version'] ?? $device?->firmware_version,
            'context' => $context ?: null,
        ]);

        $this->info("[device-log] {$serial} [{$level}] {$message}");
    }

    /**
     * Marque le terminal en ligne et, s'il vient de se reconnecter, relance
     * toute mise a jour OTA restee en attente (terminal offline au moment du push).
     */
    private function markOnlineAndRetryOta(RfidDevice $device, array $data): void
    {
        $wasOffline = ! $device->is_online;

        $updateData = ['is_online' => true, 'last_ping_at' => now()];
        if (! empty($data['firmware_version'])) {
            $updateData['firmware_version'] = $data['firmware_version'];
        }
        $device->update($updateData);
        event(new DeviceStatusUpdated('rfid', (string) $device->id, 'online', $data));

        if ($wasOffline) {
            $this->retryPendingOtaUpdates($device);
        }
    }

    /**
     * Marque le terminal hors ligne suite a un Last Will MQTT et leve l'alerte
     * correspondante. Replique le comportement de CheckDeviceHealthCommand car la
     * tache planifiee ne scanne que les terminaux is_online=true et ignorerait donc
     * un terminal deja bascule hors ligne ici.
     */
    private function markOfflineFromLwt(RfidDevice $device): void
    {
        if (! $device->is_online) {
            return;
        }

        $device->is_online = false;
        $device->save();

        $context = [
            'serial_number' => $device->serial_number ?? null,
            'last_seen' => $device->last_ping_at?->toISOString(),
            'is_witness' => (bool) ($device->is_witness ?? false),
            'reason' => 'lwt',
        ];

        try {
            event(DeviceStatusUpdated::fromDevice('rfid', $device, 'offline', 'online', [
                'last_seen' => $device->last_ping_at?->toISOString(),
                'reason' => 'lwt',
            ]));
        } catch (\Throwable $e) {
            Log::warning('[mqtt:listen-rfid] broadcast offline LWT echoue: '.$e->getMessage());
        }

        app(AlertService::class)->openOrUpdate([
            'company_id' => $device->company_id ?? null,
            'site_id' => $device->site_id ?? null,
            'device_id' => $device->id,
            'device_kind' => 'rfid',
            'type' => DeviceAlert::TYPE_OFFLINE_THRESHOLD,
            'severity' => $device->is_witness ? DeviceAlert::SEVERITY_CRITICAL : DeviceAlert::SEVERITY_HIGH,
            'title' => 'Capteur rfid hors ligne : '.($device->name ?? $device->serial_number ?? $device->id),
            'message' => 'Deconnexion detectee (Last Will MQTT). Dernier contact: '.($device->last_ping_at?->locale('fr')->diffForHumans() ?? 'inconnu'),
            'context' => $context,
        ]);
    }

    /**
     * Republie l'ordre OTA pour les logs encore en attente de ce terminal.
     * Le push MQTT initial (QoS 0) est perdu si le terminal etait offline ;
     * on rejoue donc l'ordre des qu'il se reconnecte.
     */
    private function retryPendingOtaUpdates(RfidDevice $device): void
    {
        if (empty($device->serial_number) || ! $this->mqtt) {
            return;
        }

        // Si le terminal reporte deja sa version, ne pas rejouer une OTA vers
        // une version egale ou anterieure.
        $currentVersion = $device->firmware_version;

        // pending/in_progress = push initial perdu (offline). On inclut aussi les
        // logs passes en "failed" par firmware:fail-stuck-ota (timeout = terminal
        // offline), mais PAS les vrais echecs de flash signales par le terminal.
        $logs = OtaUpdateLog::with('firmwareVersion')
            ->where('device_id', $device->id)
            ->where('device_kind', 'rfid')
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
            $topic = 'core/rfid/sensor/'.$device->serial_number.'/response';

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
