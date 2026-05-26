<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\BiometricDevice;
use App\Models\DeviceAlert;
use App\Models\FeelbackDevice;
use App\Models\QrCode;
use App\Models\RfidDevice;
use App\Services\AlertService;
use App\Services\MqttService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportController extends BaseApiController
{
    public function overview(Request $request): JsonResponse
    {
        $companyId = $request->input('company_id') ?: $this->resolveActiveCompanyId();

        $apply = function ($q) use ($companyId) {
            if ($companyId) $q->where('company_id', $companyId);
            return $q;
        };

        $stats = [
            'rfid' => [
                'total' => $apply(RfidDevice::query()->withoutGlobalScopes())->count(),
                'online' => $apply(RfidDevice::query()->withoutGlobalScopes())->where('is_online', true)->count(),
            ],
            'biometric' => [
                'total' => $apply(BiometricDevice::query()->withoutGlobalScopes())->count(),
                'online' => $apply(BiometricDevice::query()->withoutGlobalScopes())->where('is_online', true)->count(),
            ],
            'feelback' => [
                'total' => $apply(FeelbackDevice::query()->withoutGlobalScopes())->count(),
                'online' => $apply(FeelbackDevice::query()->withoutGlobalScopes())->where('is_online', true)->count(),
            ],
            'alerts' => [
                'open' => DeviceAlert::query()->where('status', DeviceAlert::STATUS_OPEN)
                    ->when($companyId, fn ($q) => $q->where('company_id', $companyId))->count(),
                'critical' => DeviceAlert::query()->where('status', DeviceAlert::STATUS_OPEN)
                    ->where('severity', DeviceAlert::SEVERITY_CRITICAL)
                    ->when($companyId, fn ($q) => $q->where('company_id', $companyId))->count(),
            ],
        ];

        return $this->successResponse($stats);
    }

    public function devices(Request $request): JsonResponse
    {
        $companyId = $request->input('company_id') ?: $this->resolveActiveCompanyId();
        $siteId = $request->input('site_id');
        $type = $request->input('type');
        $status = $request->input('status');
        $witness = $request->boolean('witness', null);

        $build = function ($model, string $kind, string $timeCol) use ($companyId, $siteId, $status, $witness) {
            $q = $model::query()->withoutGlobalScopes()->with('site:id,name');
            if ($companyId) $q->where('company_id', $companyId);
            if ($siteId) $q->where('site_id', $siteId);
            if ($status === 'online') $q->where('is_online', true);
            if ($status === 'offline') $q->where('is_online', false);
            if ($witness !== null) $q->where('is_witness', $witness);
            return $q->get()->map(fn ($d) => [
                'id' => $d->id,
                'kind' => $kind,
                'name' => $d->name ?? $d->label ?? $d->serial_number ?? $d->id,
                'serialNumber' => $d->serial_number ?? null,
                'companyId' => $d->company_id ?? null,
                'siteId' => $d->site_id ?? null,
                'siteName' => $d->site?->name,
                'isOnline' => (bool) $d->is_online,
                'isWitness' => (bool) ($d->is_witness ?? false),
                'lastSeenAt' => $d->{$timeCol}?->toIso8601String(),
                'firmwareVersion' => $d->firmware_version ?? null,
            ]);
        };

        $rfid = (!$type || $type === 'rfid') ? $build(RfidDevice::class, 'rfid', 'last_ping_at') : collect();
        $bio = (!$type || $type === 'biometric') ? $build(BiometricDevice::class, 'biometric', 'last_sync_at') : collect();
        $feel = (!$type || $type === 'feelback') ? $build(FeelbackDevice::class, 'feelback', 'last_ping_at') : collect();

        $all = $rfid->concat($bio)->concat($feel)->values();

        return $this->successResponse($all);
    }

    public function deviceDetail(string $kind, string $id): JsonResponse
    {
        $model = match ($kind) {
            'rfid' => RfidDevice::query()->withoutGlobalScopes()->with(['site:id,name', 'company:id,name'])->find($id),
            'biometric' => BiometricDevice::query()->withoutGlobalScopes()->with(['site:id,name', 'company:id,name'])->find($id),
            'feelback' => FeelbackDevice::query()->withoutGlobalScopes()->with(['site:id,name', 'company:id,name'])->find($id),
            'qr' => \App\Models\QrCode::query()->withoutGlobalScopes()->with(['site:id,name', 'company:id,name'])->find($id),
            default => null,
        };
        if (!$model) return $this->errorResponse('Capteur introuvable', 404);

        $timeCol = match ($kind) {
            'biometric' => 'last_sync_at',
            default => 'last_ping_at',
        };

        $device = [
            'id' => $model->id,
            'kind' => $kind,
            'name' => $model->name ?? $model->label ?? $model->serial_number ?? $model->id,
            'serial_number' => $model->serial_number ?? null,
            'company_id' => $model->company_id ?? null,
            'company_name' => $model->company?->name,
            'site_id' => $model->site_id ?? null,
            'site_name' => $model->site?->name,
            'is_online' => (bool) $model->is_online,
            'is_witness' => (bool) ($model->is_witness ?? false),
            'firmware_version' => $model->firmware_version ?? null,
            'last_ping_at' => $model->{$timeCol}?->toIso8601String(),
            'last_sync_at' => $kind === 'biometric' ? $model->last_sync_at?->toIso8601String() : null,
        ];

        $alerts = DeviceAlert::query()
            ->where('device_kind', $kind)
            ->where('device_id', $id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return $this->successResponse([
            'device' => $device,
            'kind' => $kind,
            'alerts' => $alerts,
        ]);
    }

    public function pingDevice(string $kind, string $id, MqttService $mqtt): JsonResponse
    {
        $device = $this->findDevice($kind, $id);
        if (!$device) return $this->errorResponse('Capteur introuvable', 404);
        if (!in_array($kind, ['rfid', 'biometric'])) {
            return $this->errorResponse('Type non supporté pour ping MQTT', 400);
        }

        $commandCode = config("mqtt.command_codes.{$kind}.STATUS");
        $prefix = config("mqtt.topics.{$kind}");
        $responseTopic = !empty($device->mqtt_topic)
            ? $mqtt->getResponseTopic($device->mqtt_topic)
            : "{$prefix}/{$device->serial_number}/response";

        try {
            $mqtt->publish($responseTopic, $commandCode);
            return $this->successResponse(['topic' => $responseTopic, 'command' => $commandCode]);
        } catch (\Throwable $e) {
            return $this->errorResponse('Echec envoi commande: ' . $e->getMessage(), 500);
        }
    }

    public function listWitnesses(): JsonResponse
    {
        $rfid = RfidDevice::query()->withoutGlobalScopes()->where('is_witness', true)->with('site:id,name')->get();
        $bio = BiometricDevice::query()->withoutGlobalScopes()->where('is_witness', true)->with('site:id,name')->get();
        $feel = FeelbackDevice::query()->withoutGlobalScopes()->where('is_witness', true)->with('site:id,name')->get();
        $qr = QrCode::query()->withoutGlobalScopes()->where('is_witness', true)->with('site:id,name')->get();

        $threshold = (int) config('devices.offline_threshold_minutes', 5);
        $cutoff = now()->subMinutes($threshold);

        // Statut "en ligne" base sur (is_online ET heartbeat recent) — sans fallback sur is_active
        // qui ne reflete pas la presence reseau et faussait l'affichage des capteurs temoin.
        $map = fn ($coll, $kind, $timeCol) => $coll->map(function ($d) use ($kind, $timeCol, $cutoff) {
            $lastSeen = $timeCol ? $d->{$timeCol} : null;
            if ($timeCol === null) {
                // Pas de mecanisme de heartbeat (ex : QR code) : statut inconnu plutot que faux positif
                $isOnline = null;
            } else {
                $isOnline = (bool) $d->is_online && $lastSeen && $lastSeen->greaterThanOrEqualTo($cutoff);
            }

            return [
                'id' => $d->id,
                'kind' => $kind,
                'name' => $d->name ?? $d->label ?? $d->serial_number ?? $d->id,
                'serialNumber' => $d->serial_number ?? null,
                'companyId' => $d->company_id ?? null,
                'siteId' => $d->site_id ?? null,
                'siteName' => $d->site?->name,
                'isOnline' => $isOnline,
                'lastSeenAt' => $lastSeen?->toIso8601String(),
                'isWitness' => true,
            ];
        });

        $all = $map($rfid, 'rfid', 'last_ping_at')
            ->concat($map($bio, 'biometric', 'last_sync_at'))
            ->concat($map($feel, 'feelback', 'last_ping_at'))
            ->concat($map($qr, 'qr', null))
            ->values();

        return $this->successResponse($all);
    }

    public function markWitness(string $kind, string $id): JsonResponse
    {
        $device = $this->findDevice($kind, $id);
        if (!$device) return $this->errorResponse('Capteur introuvable', 404);
        $device->is_witness = true;
        $device->save();
        return $this->successResponse(['id' => $id, 'kind' => $kind, 'isWitness' => true]);
    }

    public function unmarkWitness(string $kind, string $id): JsonResponse
    {
        $device = $this->findDevice($kind, $id);
        if (!$device) return $this->errorResponse('Capteur introuvable', 404);
        $device->is_witness = false;
        $device->save();
        return $this->successResponse(['id' => $id, 'kind' => $kind, 'isWitness' => false]);
    }

    public function alerts(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $q = DeviceAlert::query()
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('severity'), fn ($q, $v) => $q->where('severity', $v))
            ->when($request->input('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->input('company_id'), fn ($q, $v) => $q->where('company_id', $v))
            ->orderByDesc('created_at');

        $paginator = $q->paginate($perPage);
        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
            ],
        ]);
    }

    public function acknowledgeAlert(string $id): JsonResponse
    {
        $alert = DeviceAlert::findOrFail($id);
        $alert->update([
            'status' => DeviceAlert::STATUS_ACKNOWLEDGED,
            'acknowledged_by' => auth()->id(),
            'acknowledged_at' => now(),
        ]);
        return $this->successResponse($alert);
    }

    public function resolveAlert(string $id, AlertService $svc): JsonResponse
    {
        $alert = DeviceAlert::findOrFail($id);
        $svc->resolve($alert);
        return $this->successResponse($alert->refresh());
    }

    protected function findDevice(string $kind, string $id)
    {
        return match ($kind) {
            'rfid' => RfidDevice::query()->withoutGlobalScopes()->find($id),
            'biometric' => BiometricDevice::query()->withoutGlobalScopes()->find($id),
            'feelback' => FeelbackDevice::query()->withoutGlobalScopes()->find($id),
            'qr' => QrCode::query()->withoutGlobalScopes()->find($id),
            default => null,
        };
    }
}
