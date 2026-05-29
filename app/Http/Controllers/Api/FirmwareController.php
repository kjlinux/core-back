<?php

namespace App\Http\Controllers\Api;

use App\Events\NotificationReceived;
use App\Mail\FirmwareUpdateAvailableMail;
use App\Models\AppNotification;
use App\Models\FirmwareVersion;
use App\Models\OtaUpdateLog;
use App\Models\RfidDevice;
use App\Models\BiometricDevice;
use App\Models\User;
use App\Http\Resources\FirmwareVersionResource;
use App\Http\Resources\OtaUpdateLogResource;
use App\Services\MqttService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FirmwareController extends BaseApiController
{
    /**
     * Endpoint public pour les capteurs ESP32 — vérification automatique OTA.
     * GET /firmware/version.json?device_kind=rfid
     * Reponse : {"version":"V2.0.4","url":"https://..."}
     */
    public function latestVersion(Request $request): JsonResponse
    {
        $deviceKind = $request->input('device_kind', 'rfid');

        $latest = FirmwareVersion::where('device_kind', $deviceKind)
            ->where('is_published', true)
            ->orderByDesc('created_at')
            ->first();

        if (!$latest) {
            return response()->json(['version' => null, 'url' => null]);
        }

        $fileUrl = $latest->file_path
            ? rtrim(config('app.url'), '/') . '/storage/' . $latest->file_path
            : null;

        return response()->json([
            'version' => $latest->version,
            'url'     => $fileUrl,
        ]);
    }

    public function versions(Request $request): JsonResponse
    {
        $query = FirmwareVersion::with('uploader')
            ->when($request->input('device_kind'), fn($q, $v) => $q->where('device_kind', $v))
            ->orderBy('created_at', 'desc');

        $versions = $query->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(FirmwareVersionResource::collection($versions));
    }

    public function showVersion(string $id): JsonResponse
    {
        $version = FirmwareVersion::with('uploader')->findOrFail($id);
        return $this->resourceResponse(new FirmwareVersionResource($version));
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimetypes:application/octet-stream,application/macbinary,application/x-binary,application/x-msdownload,application/x-dosexec,application/x-executable,text/plain|max:10240',
            'version' => [
                'required',
                'string',
                'max:20',
                Rule::unique('firmware_versions', 'version')
                    ->where(fn ($query) => $query->where('device_kind', $request->input('device_kind'))),
            ],
            'device_kind' => 'required|in:rfid,biometric',
            'description' => 'nullable|string|max:500',
            'is_auto_update' => 'nullable|boolean',
        ], [
            'version.unique' => 'Cette version existe deja pour ce type d\'appareil.',
        ]);

        $file = $request->file('file');
        $path = $file->store("firmware/{$request->input('device_kind')}", 'public');

        $isAuto = $request->boolean('is_auto_update');

        $version = FirmwareVersion::create([
            'version' => $request->input('version'),
            'device_kind' => $request->input('device_kind'),
            'description' => $request->input('description'),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'is_auto_update' => $isAuto,
            // Auto-update implique publication immediate : les terminaux offline
            // recuperent la MAJ via la verification horaire /firmware/version.json
            'is_published' => $isAuto,
            'published_at' => $isAuto ? now() : null,
            'uploaded_by' => Auth::id(),
        ]);

        $version->load('uploader');

        // Push MQTT immediat aux terminaux en ligne + notification (version publiee)
        if ($version->is_auto_update) {
            $this->scheduleAutoUpdates($version);
            $this->notifyPublication($version);
        }

        return $this->resourceResponse(new FirmwareVersionResource($version), 'Firmware mis en ligne avec succes', 201);
    }

    public function deleteVersion(string $id): JsonResponse
    {
        $version = FirmwareVersion::findOrFail($id);

        if ($version->file_path) {
            Storage::disk('public')->delete($version->file_path);
        }

        $version->delete();

        return $this->noContentResponse();
    }

    public function setAutoUpdate(Request $request, string $id): JsonResponse
    {
        $request->validate(['is_auto_update' => 'required|boolean']);

        $version = FirmwareVersion::findOrFail($id);
        $version->update(['is_auto_update' => $request->boolean('is_auto_update')]);

        return $this->resourceResponse(new FirmwareVersionResource($version));
    }

    public function deviceStatuses(Request $request): JsonResponse
    {
        $deviceKind = $request->input('device_kind');
        $statuses = [];

        // Version cible = dernière version publiée (auto-update ou non) par type
        $latestRfid = FirmwareVersion::where('device_kind', 'rfid')
            ->where('is_published', true)
            ->orderByDesc('created_at')
            ->first();
        $latestBio = FirmwareVersion::where('device_kind', 'biometric')
            ->where('is_published', true)
            ->orderByDesc('created_at')
            ->first();

        if (!$deviceKind || $deviceKind === 'rfid') {
            $rfidQuery = RfidDevice::query();
            $this->scopeByCompany($rfidQuery);
            $rfidDevices = $rfidQuery->get();

            // Charger tous les derniers logs en une seule requete (evite N+1)
            $rfidDeviceIds = $rfidDevices->pluck('id')->all();
            $rfidLatestLogs = OtaUpdateLog::whereIn('device_id', $rfidDeviceIds)
                ->where('device_kind', 'rfid')
                ->orderByDesc('created_at')
                ->get()
                ->unique('device_id')
                ->keyBy('device_id');

            foreach ($rfidDevices as $device) {
                $lastLog = $rfidLatestLogs->get($device->id);

                $statuses[] = [
                    'deviceId'       => (string) $device->id,
                    'deviceName'     => $device->name,
                    'deviceKind'     => 'rfid',
                    'currentVersion' => $device->firmware_version ?? 'inconnue',
                    'targetVersion'  => $latestRfid?->version,
                    'updateStatus'   => $lastLog?->status ?? 'skipped',
                    'lastCheckedAt'  => $device->last_ping_at?->toISOString() ?? now()->toISOString(),
                    'lastUpdatedAt'  => $lastLog?->completed_at?->toISOString(),
                ];
            }
        }

        if (!$deviceKind || $deviceKind === 'biometric') {
            $bioQuery = BiometricDevice::query();
            $this->scopeByCompany($bioQuery);
            $bioDevices = $bioQuery->get();

            // Charger tous les derniers logs en une seule requete (evite N+1)
            $bioDeviceIds = $bioDevices->pluck('id')->all();
            $bioLatestLogs = OtaUpdateLog::whereIn('device_id', $bioDeviceIds)
                ->where('device_kind', 'biometric')
                ->orderByDesc('created_at')
                ->get()
                ->unique('device_id')
                ->keyBy('device_id');

            foreach ($bioDevices as $device) {
                $lastLog = $bioLatestLogs->get($device->id);

                $statuses[] = [
                    'deviceId'       => (string) $device->id,
                    'deviceName'     => $device->name,
                    'deviceKind'     => 'biometric',
                    'currentVersion' => $device->firmware_version ?? 'inconnue',
                    'targetVersion'  => $latestBio?->version,
                    'updateStatus'   => $lastLog?->status ?? 'skipped',
                    'lastCheckedAt'  => $device->last_sync_at?->toISOString() ?? now()->toISOString(),
                    'lastUpdatedAt'  => $lastLog?->completed_at?->toISOString(),
                ];
            }
        }

        return $this->successResponse($statuses);
    }

    public function triggerUpdate(Request $request, MqttService $mqtt): JsonResponse
    {
        $request->validate([
            'device_id' => 'required|uuid',
            'firmware_version_id' => 'required|uuid|exists:firmware_versions,id',
        ]);

        $firmwareVersion = FirmwareVersion::findOrFail($request->input('firmware_version_id'));
        $deviceId = $request->input('device_id');
        $deviceKind = $firmwareVersion->device_kind;

        $device = $deviceKind === 'rfid'
            ? RfidDevice::findOrFail($deviceId)
            : BiometricDevice::findOrFail($deviceId);

        if (empty($device->serial_number)) {
            return $this->errorResponse('Ce terminal n\'a pas de numero de serie. Impossible d\'envoyer la commande OTA.', 422);
        }

        // Idempotence : ne pas redeclencher si une OTA est deja en cours pour ce couple device+version
        $existing = OtaUpdateLog::where('device_id', $deviceId)
            ->where('firmware_version_id', $firmwareVersion->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->where('started_at', '>=', now()->subSeconds(60))
            ->first();
        if ($existing) {
            $existing->load('firmwareVersion');
            return $this->resourceResponse(new OtaUpdateLogResource($existing), 'Mise a jour deja en cours pour ce terminal', 200);
        }

        $log = OtaUpdateLog::create([
            'device_id' => $deviceId,
            'device_kind' => $deviceKind,
            'firmware_version_id' => $firmwareVersion->id,
            'status' => 'pending',
            'triggered_by' => 'manual',
            'started_at' => now(),
        ]);

        $this->dispatchOtaCommand($mqtt, $deviceKind, $device->serial_number, $firmwareVersion, $log);

        $log->load('firmwareVersion');

        return $this->resourceResponse(new OtaUpdateLogResource($log), 'Mise a jour declenchee', 201);
    }

    /**
     * Publie l'ordre OTA sur le topic ecoute par l'ESP32 et met a jour le log.
     * Topic : core/{kind}/sensor/{device_id}/response
     * Payload : { cmd: "0x1080D0", url, version, log_id }
     */
    private function dispatchOtaCommand(MqttService $mqtt, string $deviceKind, string $serialNumber, FirmwareVersion $firmwareVersion, OtaUpdateLog $log): void
    {
        $fileUrl = $firmwareVersion->file_path
            ? rtrim(config('app.url'), '/') . '/storage/' . $firmwareVersion->file_path
            : null;

        try {
            $topic = "core/{$deviceKind}/sensor/{$serialNumber}/response";
            $mqtt->publish($topic, json_encode([
                'cmd'     => '0x1080D0',
                'url'     => $fileUrl,
                'version' => $firmwareVersion->version,
                'log_id'  => (string) $log->id,
            ]));
            $log->update(['status' => 'in_progress']);
        } catch (\Exception $e) {
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage(), 'completed_at' => now()]);
        }
    }

    public function logs(Request $request): JsonResponse
    {
        $query = OtaUpdateLog::with('firmwareVersion')
            ->when($request->input('device_id'), fn($q, $v) => $q->where('device_id', $v))
            ->when($request->input('device_kind'), fn($q, $v) => $q->where('device_kind', $v))
            ->when($request->input('status'), fn($q, $v) => $q->where('status', $v))
            ->orderBy('started_at', 'desc');

        $logs = $query->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(OtaUpdateLogResource::collection($logs));
    }

    /**
     * Publie une version firmware et notifie tous les admins/techniciens par email + notification in-app.
     */
    public function publish(string $id): JsonResponse
    {
        $firmware = FirmwareVersion::findOrFail($id);

        if ($firmware->is_published) {
            return $this->errorResponse('Cette version est deja publiee', 422);
        }

        $firmware->update([
            'is_published' => true,
            'published_at' => now(),
        ]);

        $count = $this->notifyPublication($firmware);

        $firmware->load('uploader');

        return $this->resourceResponse(
            new FirmwareVersionResource($firmware),
            "Version publiee. {$count} utilisateur(s) notifie(s)."
        );
    }

    /**
     * Notifie les admins/techniciens (email + in-app) qu'une version est publiee.
     * Partage entre publish() et upload() auto-update. Retourne le nb de destinataires.
     */
    private function notifyPublication(FirmwareVersion $firmware): int
    {
        $kind = $firmware->device_kind === 'rfid' ? 'RFID' : 'Biometrique';
        $recipients = User::whereIn('role', ['admin_enterprise', 'technicien'])
            ->where('is_active', true)
            ->get();

        foreach ($recipients as $user) {
            Mail::to($user->email)->queue(new FirmwareUpdateAvailableMail($firmware));

            $notification = AppNotification::create([
                'user_id' => $user->id,
                'type'    => 'firmware_update',
                'title'   => 'Mise a jour firmware disponible',
                'message' => "La version {$firmware->version} est disponible pour vos capteurs {$kind}.",
                'is_read' => false,
                'data'    => ['firmware_version_id' => (string) $firmware->id, 'device_kind' => $firmware->device_kind],
            ]);

            event(new NotificationReceived($notification));
        }

        return $recipients->count();
    }

    /**
     * Déclenche la mise à jour OTA sur tous les capteurs de la company de l'utilisateur connecté.
     */
    public function triggerCompanyUpdate(Request $request, MqttService $mqtt): JsonResponse
    {
        $request->validate([
            'firmware_version_id' => 'required|uuid|exists:firmware_versions,id',
        ]);

        $firmware = FirmwareVersion::findOrFail($request->input('firmware_version_id'));
        $companyId = $this->resolveActiveCompanyId();

        $deviceModel = $firmware->device_kind === 'rfid' ? RfidDevice::class : BiometricDevice::class;
        $query = $deviceModel::query();
        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        $devices = $query->get();

        $logs = [];
        foreach ($devices as $device) {
            if (empty($device->serial_number)) {
                continue;
            }

            // Idempotence par device
            $existing = OtaUpdateLog::where('device_id', $device->id)
                ->where('firmware_version_id', $firmware->id)
                ->whereIn('status', ['pending', 'in_progress'])
                ->where('started_at', '>=', now()->subSeconds(60))
                ->first();
            if ($existing) {
                $existing->load('firmwareVersion');
                $logs[] = new OtaUpdateLogResource($existing);
                continue;
            }

            $log = OtaUpdateLog::create([
                'device_id'           => $device->id,
                'device_kind'         => $firmware->device_kind,
                'firmware_version_id' => $firmware->id,
                'status'              => 'pending',
                'triggered_by'        => 'manual',
                'started_at'          => now(),
            ]);

            $this->dispatchOtaCommand($mqtt, $firmware->device_kind, $device->serial_number, $firmware, $log);

            $log->load('firmwareVersion');
            $logs[] = new OtaUpdateLogResource($log);
        }

        return $this->successResponse([
            'triggered' => count($logs),
            'logs'      => $logs,
        ], 'Mise a jour declenchee sur ' . count($logs) . ' capteur(s)');
    }

    /**
     * Retourne la progression de la mise à jour en masse pour la company de l'utilisateur.
     */
    public function companyUpdateProgress(Request $request): JsonResponse
    {
        $request->validate([
            'firmware_version_id' => 'required|uuid|exists:firmware_versions,id',
        ]);

        $firmware = FirmwareVersion::findOrFail($request->input('firmware_version_id'));
        $companyId = $this->resolveActiveCompanyId();

        $deviceModel = $firmware->device_kind === 'rfid' ? RfidDevice::class : BiometricDevice::class;
        $query = $deviceModel::query();
        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        $devices = $query->get();

        // Charger tous les logs pertinents en une seule requete (evite N+1)
        $deviceIds = $devices->pluck('id')->all();
        $latestLogsByDevice = OtaUpdateLog::whereIn('device_id', $deviceIds)
            ->where('firmware_version_id', $firmware->id)
            ->orderByDesc('started_at')
            ->get()
            ->unique('device_id')
            ->keyBy('device_id');

        $result = [
            'total'      => $devices->count(),
            'pending'    => 0,
            'inProgress' => 0,
            'success'    => 0,
            'failed'     => 0,
            'devices'    => [],
        ];

        foreach ($devices as $device) {
            $log = $latestLogsByDevice->get($device->id);

            $status = $log?->status ?? 'pending';

            match ($status) {
                'pending'     => $result['pending']++,
                'in_progress' => $result['inProgress']++,
                'success'     => $result['success']++,
                'failed'      => $result['failed']++,
                default       => null,
            };

            $result['devices'][] = [
                'deviceId'     => (string) $device->id,
                'deviceName'   => $device->name,
                'deviceKind'   => $firmware->device_kind,
                'status'       => $status,
                'errorMessage' => $log?->error_message,
                'completedAt'  => $log?->completed_at?->toISOString(),
            ];
        }

        return $this->successResponse($result);
    }

    /**
     * Relance les capteurs en échec pour une version donnée.
     */
    public function retryFailed(Request $request, MqttService $mqtt): JsonResponse
    {
        $request->validate([
            'firmware_version_id' => 'required|uuid|exists:firmware_versions,id',
        ]);

        $firmware = FirmwareVersion::findOrFail($request->input('firmware_version_id'));
        $companyId = $this->resolveActiveCompanyId();

        $deviceModel = $firmware->device_kind === 'rfid' ? RfidDevice::class : BiometricDevice::class;
        $deviceIds = $deviceModel::query()
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->pluck('id');

        $failedLogs = OtaUpdateLog::whereIn('device_id', $deviceIds)
            ->where('firmware_version_id', $firmware->id)
            ->where('status', 'failed')
            ->get();

        $triggered = 0;
        foreach ($failedLogs as $failedLog) {
            $device = $deviceModel::find($failedLog->device_id);
            if (!$device || empty($device->serial_number)) continue;

            $log = OtaUpdateLog::create([
                'device_id'           => $device->id,
                'device_kind'         => $firmware->device_kind,
                'firmware_version_id' => $firmware->id,
                'status'              => 'pending',
                'triggered_by'        => 'manual',
                'started_at'          => now(),
            ]);

            $this->dispatchOtaCommand($mqtt, $firmware->device_kind, $device->serial_number, $firmware, $log);
            if ($log->fresh()->status === 'in_progress') {
                $triggered++;
            }
        }

        return $this->successResponse(
            ['triggered' => $triggered],
            "{$triggered} capteur(s) relance(s)"
        );
    }

    /**
     * Re-pousse l'ordre OTA aux capteurs encore en attente (offline au moment
     * du push initial). Rejoue le MQTT pour les logs pending/in_progress.
     */
    public function retryPending(Request $request, MqttService $mqtt): JsonResponse
    {
        $request->validate([
            'firmware_version_id' => 'required|uuid|exists:firmware_versions,id',
        ]);

        $firmware = FirmwareVersion::findOrFail($request->input('firmware_version_id'));
        $companyId = $this->resolveActiveCompanyId();

        $deviceModel = $firmware->device_kind === 'rfid' ? RfidDevice::class : BiometricDevice::class;
        $deviceIds = $deviceModel::query()
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->pluck('id');

        $pendingLogs = OtaUpdateLog::whereIn('device_id', $deviceIds)
            ->where('firmware_version_id', $firmware->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->orderByDesc('started_at')
            ->get()
            ->unique('device_id');

        $triggered = 0;
        foreach ($pendingLogs as $log) {
            $device = $deviceModel::find($log->device_id);
            if (! $device || empty($device->serial_number)) {
                continue;
            }

            $this->dispatchOtaCommand($mqtt, $firmware->device_kind, $device->serial_number, $firmware, $log);
            $triggered++;
        }

        return $this->successResponse(
            ['triggered' => $triggered],
            "{$triggered} capteur(s) relance(s)"
        );
    }

    private function scheduleAutoUpdates(FirmwareVersion $firmwareVersion): void
    {
        $mqtt = app(MqttService::class);
        $model = $firmwareVersion->device_kind === 'rfid' ? RfidDevice::class : BiometricDevice::class;

        // Scope par company de l'utilisateur connecté (multi-tenant)
        $companyId = $this->resolveActiveCompanyId();
        $query = $model::query();
        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        // Ignorer les terminaux sans serial_number (impossible d'envoyer le MQTT)
        $query->whereNotNull('serial_number')->where('serial_number', '!=', '');
        $devices = $query->get();

        foreach ($devices as $device) {
            $log = OtaUpdateLog::create([
                'device_id'           => $device->id,
                'device_kind'         => $firmwareVersion->device_kind,
                'firmware_version_id' => $firmwareVersion->id,
                'status'              => 'pending',
                'triggered_by'        => 'auto',
                'started_at'          => now(),
            ]);

            $this->dispatchOtaCommand($mqtt, $firmwareVersion->device_kind, $device->serial_number, $firmwareVersion, $log);
        }
    }
}
