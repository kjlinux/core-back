<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\UserResource;
use App\Models\BiometricDevice;
use App\Models\Company;
use App\Models\DeviceAlert;
use App\Models\FeelbackDevice;
use App\Models\QrCode;
use App\Models\RfidDevice;
use App\Models\User;
use App\Services\AlertService;
use App\Services\MqttService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SupportController extends BaseApiController
{
    public function overview(Request $request): JsonResponse
    {
        $companyId = $request->input('company_id') ?: $this->resolveActiveCompanyId();
        $cutoff = now()->subMinutes((int) config('devices.offline_threshold_minutes', 5));

        $apply = function ($q) use ($companyId) {
            if ($companyId) {
                $q->where('company_id', $companyId);
            }

            return $q;
        };

        // « En ligne » = is_online ET dernier signal récent (heartbeat), même
        // définition que les pages Compagnies / Capteurs pour éviter des chiffres
        // contradictoires entre le tableau de bord et les listes détaillées.
        $online = fn ($model, string $timeCol) => $apply($model::query()->withoutGlobalScopes())
            ->where('is_online', true)
            ->where($timeCol, '>=', $cutoff)
            ->count();

        $stats = [
            'rfid' => [
                'total' => $apply(RfidDevice::query()->withoutGlobalScopes())->count(),
                'online' => $online(RfidDevice::class, 'last_ping_at'),
            ],
            'biometric' => [
                'total' => $apply(BiometricDevice::query()->withoutGlobalScopes())->count(),
                'online' => $online(BiometricDevice::class, 'last_sync_at'),
            ],
            'feelback' => [
                'total' => $apply(FeelbackDevice::query()->withoutGlobalScopes())->count(),
                'online' => $online(FeelbackDevice::class, 'last_ping_at'),
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
        $search = trim((string) $request->input('search', ''));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, (int) $request->input('per_page', 15));

        $cutoff = now()->subMinutes((int) config('devices.offline_threshold_minutes', 5));

        $build = function ($model, string $kind, string $timeCol) use ($companyId, $siteId, $witness) {
            $q = $model::query()->withoutGlobalScopes()->with('site:id,name');
            if ($companyId) {
                $q->where('company_id', $companyId);
            }
            if ($siteId) {
                $q->where('site_id', $siteId);
            }
            if ($witness !== null) {
                $q->where('is_witness', $witness);
            }

            return $q->get()->map(function ($d) use ($kind, $timeCol) {
                $lastSeen = $d->{$timeCol};

                return [
                    'id' => $d->id,
                    'kind' => $kind,
                    'name' => $d->name ?? $d->label ?? $d->serial_number ?? $d->id,
                    'serialNumber' => $d->serial_number ?? null,
                    'companyId' => $d->company_id ?? null,
                    'siteId' => $d->site_id ?? null,
                    'siteName' => $d->site?->name,
                    'isOnline' => (bool) $d->is_online && $lastSeen && $lastSeen->greaterThanOrEqualTo($cutoff),
                    'isWitness' => (bool) ($d->is_witness ?? false),
                    'lastSeenAt' => $lastSeen?->toIso8601String(),
                    'firmwareVersion' => $d->firmware_version ?? null,
                    '_sort' => $lastSeen?->getTimestamp() ?? 0,
                ];
            });
        };

        $rfid = (! $type || $type === 'rfid') ? $build(RfidDevice::class, 'rfid', 'last_ping_at') : collect();
        $bio = (! $type || $type === 'biometric') ? $build(BiometricDevice::class, 'biometric', 'last_sync_at') : collect();
        $feel = (! $type || $type === 'feelback') ? $build(FeelbackDevice::class, 'feelback', 'last_ping_at') : collect();

        $all = $rfid->concat($bio)->concat($feel);

        if ($status === 'online') {
            $all = $all->where('isOnline', true);
        } elseif ($status === 'offline') {
            $all = $all->where('isOnline', false);
        }

        if ($search !== '') {
            $all = $all->filter(fn ($d) => $this->matchesSearch($d, $search, ['name', 'serialNumber', 'siteName']));
        }

        $all = $all->sortByDesc('_sort')->map(fn ($d) => Arr::except($d, ['_sort']))->values();

        return $this->paginateCollection($all, $page, $perPage);
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
        if (! $model) {
            return $this->errorResponse('Capteur introuvable', 404);
        }

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
        if (! $device) {
            return $this->errorResponse('Capteur introuvable', 404);
        }
        if (! in_array($kind, ['rfid', 'biometric'])) {
            return $this->errorResponse('Type non supporté pour ping MQTT', 400);
        }

        $commandCode = config("mqtt.command_codes.{$kind}.STATUS");
        $prefix = config("mqtt.topics.{$kind}");
        $responseTopic = ! empty($device->mqtt_topic)
            ? $mqtt->getResponseTopic($device->mqtt_topic)
            : "{$prefix}/{$device->serial_number}/response";

        try {
            $mqtt->publish($responseTopic, $commandCode);

            return $this->successResponse(['topic' => $responseTopic, 'command' => $commandCode]);
        } catch (\Throwable $e) {
            return $this->errorResponse('Echec envoi commande: '.$e->getMessage(), 500);
        }
    }

    public function sendCommand(Request $request, string $kind, string $id, MqttService $mqtt): JsonResponse
    {
        $device = $this->findDevice($kind, $id);
        if (! $device) {
            return $this->errorResponse('Capteur introuvable', 404);
        }
        if (! in_array($kind, ['rfid', 'biometric'])) {
            return $this->errorResponse('Type non supporté pour commande MQTT', 400);
        }

        $command = strtoupper((string) $request->input('command'));
        if (! in_array($command, ['STATUS', 'REBOOT', 'RESET'])) {
            return $this->errorResponse('Commande non autorisée', 422);
        }

        $commandCode = config("mqtt.command_codes.{$kind}.{$command}");
        if ($commandCode === null) {
            return $this->errorResponse('Commande indisponible pour ce type', 422);
        }

        $prefix = config("mqtt.topics.{$kind}");
        $responseTopic = ! empty($device->mqtt_topic)
            ? $mqtt->getResponseTopic($device->mqtt_topic)
            : "{$prefix}/{$device->serial_number}/response";

        try {
            $mqtt->publish($responseTopic, $commandCode);
            Log::info('[Support] commande capteur', [
                'support_user_id' => auth()->id(),
                'kind' => $kind,
                'device_id' => $id,
                'command' => $command,
            ]);

            return $this->successResponse(['topic' => $responseTopic, 'command' => $command]);
        } catch (\Throwable $e) {
            return $this->errorResponse('Echec envoi commande: '.$e->getMessage(), 500);
        }
    }

    public function listWitnesses(Request $request): JsonResponse
    {
        $companyId = $request->input('company_id') ?: $this->resolveActiveCompanyId();
        $search = trim((string) $request->input('search', ''));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, (int) $request->input('per_page', 15));

        $applyCompany = fn ($q) => $companyId ? $q->where('company_id', $companyId) : $q;

        $rfid = $applyCompany(RfidDevice::query()->withoutGlobalScopes()->where('is_witness', true)->with('site:id,name'))->get();
        $bio = $applyCompany(BiometricDevice::query()->withoutGlobalScopes()->where('is_witness', true)->with('site:id,name'))->get();
        $feel = $applyCompany(FeelbackDevice::query()->withoutGlobalScopes()->where('is_witness', true)->with('site:id,name'))->get();
        $qr = $applyCompany(QrCode::query()->withoutGlobalScopes()->where('is_witness', true)->with('site:id,name'))->get();

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

        if ($search !== '') {
            $all = $all->filter(fn ($d) => $this->matchesSearch($d, $search, ['name', 'serialNumber', 'siteName']))->values();
        }

        // Plus recemment actifs en premier (lastSeenAt ISO8601 trie lexicalement = chronologiquement ; null en dernier).
        $all = $all->sortByDesc('lastSeenAt')->values();

        return $this->paginateCollection($all, $page, $perPage);
    }

    public function markWitness(string $kind, string $id): JsonResponse
    {
        $device = $this->findDevice($kind, $id);
        if (! $device) {
            return $this->errorResponse('Capteur introuvable', 404);
        }
        $device->is_witness = true;
        $device->save();

        return $this->successResponse(['id' => $id, 'kind' => $kind, 'isWitness' => true]);
    }

    public function unmarkWitness(string $kind, string $id): JsonResponse
    {
        $device = $this->findDevice($kind, $id);
        if (! $device) {
            return $this->errorResponse('Capteur introuvable', 404);
        }
        $device->is_witness = false;
        $device->save();

        return $this->successResponse(['id' => $id, 'kind' => $kind, 'isWitness' => false]);
    }

    public function alerts(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $companyId = $request->input('company_id') ?: $this->resolveActiveCompanyId();
        $q = DeviceAlert::query()
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('severity'), fn ($q, $v) => $q->where('severity', $v))
            ->when($request->input('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($companyId, fn ($q, $v) => $q->where('company_id', $v))
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

    public function companies(Request $request): JsonResponse
    {
        $threshold = (int) config('devices.offline_threshold_minutes', 5);
        $cutoff = now()->subMinutes($threshold);
        $search = trim((string) $request->input('search', ''));
        $perPage = max(1, (int) $request->input('per_page', 15));
        $page = max(1, (int) $request->input('page', 1));

        // Recherche et pagination en SQL sur `companies` ; l'enrichissement coûteux
        // (compteurs capteurs / alertes) n'est calculé que pour la page courante.
        $paginator = Company::query()
            ->when($search !== '', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%']))
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $rows = collect($paginator->items())->map(function (Company $c) use ($cutoff) {
            $counts = $this->deviceCountsForCompany($c->id, $cutoff);

            return [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'phone' => $c->phone,
                'isActive' => (bool) $c->is_active,
                'devicesTotal' => $counts['total'],
                'devicesOnline' => $counts['online'],
                'devicesOffline' => $counts['total'] - $counts['online'],
                'oldestOfflineSince' => $counts['oldestOfflineSince'],
                'openAlerts' => DeviceAlert::query()
                    ->where('company_id', $c->id)
                    ->whereIn('status', [DeviceAlert::STATUS_OPEN, DeviceAlert::STATUS_ACKNOWLEDGED])
                    ->count(),
            ];
        });

        return response()->json([
            'data' => $rows->values(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
            ],
        ]);
    }

    public function companyDetail(string $id): JsonResponse
    {
        $company = Company::query()->find($id);
        if (! $company) {
            return $this->errorResponse('Compagnie introuvable', 404);
        }

        $threshold = (int) config('devices.offline_threshold_minutes', 5);
        $cutoff = now()->subMinutes($threshold);
        $counts = $this->deviceCountsForCompany($id, $cutoff);

        $users = User::query()->where('company_id', $id)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'phone', 'role', 'is_active'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => trim(($u->first_name ?? '').' '.($u->last_name ?? '')) ?: $u->email,
                'email' => $u->email,
                'phone' => $u->phone,
                'role' => $u->role,
                'isActive' => (bool) $u->is_active,
            ]);

        $alerts = DeviceAlert::query()
            ->where('company_id', $id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return $this->successResponse([
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'email' => $company->email,
                'phone' => $company->phone,
                'address' => $company->address,
                'isActive' => (bool) $company->is_active,
            ],
            'devices' => $counts,
            'users' => $users,
            'alerts' => $alerts,
        ]);
    }

    public function resetUserPassword(string $userId): JsonResponse
    {
        $user = User::query()->find($userId);
        if (! $user) {
            return $this->errorResponse('Utilisateur introuvable', 404);
        }
        if ($user->role === 'super_admin') {
            return $this->errorResponse('Action non autorisée sur un super administrateur', 403);
        }

        $tempPassword = Str::password(12, true, true, false);
        $user->update(['password' => Hash::make($tempPassword)]);
        $user->tokens()->delete();

        Log::info('[Support] reset mot de passe utilisateur', [
            'support_user_id' => auth()->id(),
            'target_user_id' => $user->id,
            'company_id' => $user->company_id,
        ]);

        return $this->successResponse([
            'userId' => $user->id,
            'tempPassword' => $tempPassword,
        ]);
    }

    /**
     * Définition manuelle d'un mot de passe pour un utilisateur (saisi par le support).
     * Les sessions de l'utilisateur sont révoquées pour forcer une reconnexion.
     */
    public function setUserPassword(Request $request, string $userId): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::query()->find($userId);
        if (! $user) {
            return $this->errorResponse('Utilisateur introuvable', 404);
        }
        if ($user->role === 'super_admin') {
            return $this->errorResponse('Action non autorisée sur un super administrateur', 403);
        }

        $user->update(['password' => Hash::make($validated['password'])]);
        $user->tokens()->delete();

        Log::info('[Support] définition manuelle du mot de passe utilisateur', [
            'support_user_id' => auth()->id(),
            'target_user_id' => $user->id,
            'company_id' => $user->company_id,
        ]);

        return $this->successResponse(['userId' => $user->id]);
    }

    /**
     * Prise de contrôle d'une entreprise : émet un token agissant comme son compte
     * administrateur (admin_enterprise actif le plus ancien).
     */
    public function impersonateCompany(string $id): JsonResponse
    {
        $company = Company::query()->find($id);
        if (! $company) {
            return $this->errorResponse('Compagnie introuvable', 404);
        }

        $target = User::query()
            ->where('company_id', $id)
            ->where('role', 'admin_enterprise')
            ->where('is_active', true)
            ->oldest('id')
            ->first();

        if (! $target) {
            return $this->errorResponse('Aucun compte administrateur actif à contrôler pour cette entreprise', 422);
        }

        return $this->successResponse($this->buildImpersonationPayload($target));
    }

    /**
     * Prise de contrôle d'un utilisateur précis (depuis la fiche entreprise).
     */
    public function impersonateUser(string $userId): JsonResponse
    {
        $target = User::query()->with('company')->find($userId);
        if (! $target) {
            return $this->errorResponse('Utilisateur introuvable', 404);
        }
        if ($target->role === 'super_admin') {
            return $this->errorResponse('Action non autorisée sur un super administrateur', 403);
        }
        if ((string) $target->id === (string) auth()->id()) {
            return $this->errorResponse('Action non autorisée', 422);
        }
        if (! $target->company_id) {
            return $this->errorResponse("Cet utilisateur n'est rattaché à aucune entreprise", 422);
        }

        return $this->successResponse($this->buildImpersonationPayload($target));
    }

    /**
     * Émet le token d'impersonation pour le compte cible, journalise l'action et
     * renvoie le payload attendu par le front (token + user + identité du support).
     *
     * @return array<string, mixed>
     */
    protected function buildImpersonationPayload(User $target): array
    {
        $support = auth()->user();
        $target->loadMissing('company');

        $token = $target->createToken(
            'impersonation:by='.$support->id,
            ['*'],
            now()->addHours(2),
        )->plainTextToken;

        Log::warning('[Support] prise de controle compte', [
            'support_user_id' => $support->id,
            'target_user_id' => $target->id,
            'company_id' => $target->company_id,
        ]);

        return [
            'accessToken' => $token,
            'user' => new UserResource($target),
            'impersonator' => [
                'id' => (string) $support->id,
                'name' => trim(($support->first_name ?? '').' '.($support->last_name ?? '')) ?: $support->email,
            ],
        ];
    }

    /**
     * Filtre une ligne de capteur normalisée par recherche texte sur des champs donnés.
     *
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $fields
     */
    protected function matchesSearch(array $row, string $search, array $fields): bool
    {
        $needle = mb_strtolower($search);
        foreach ($fields as $field) {
            if (str_contains(mb_strtolower((string) ($row[$field] ?? '')), $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pagine une collection en mémoire et renvoie l'enveloppe { data, meta } attendue
     * par le front (PaginatedResponse). Utilisé pour les listes fusionnées multi-modèles.
     */
    protected function paginateCollection(Collection $items, int $page, int $perPage): JsonResponse
    {
        $total = $items->count();
        $totalPages = (int) max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $slice = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'data' => $slice,
            'meta' => [
                'currentPage' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
        ]);
    }

    protected function deviceCountsForCompany(string $companyId, $cutoff): array
    {
        $total = 0;
        $online = 0;
        $oldest = null;

        $specs = [
            [RfidDevice::class, 'last_ping_at'],
            [BiometricDevice::class, 'last_sync_at'],
            [FeelbackDevice::class, 'last_ping_at'],
        ];

        foreach ($specs as [$model, $timeCol]) {
            $devices = $model::query()->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->get(['id', 'is_online', $timeCol]);

            foreach ($devices as $d) {
                $total++;
                $lastSeen = $d->{$timeCol};
                $isOnline = (bool) $d->is_online && $lastSeen && $lastSeen->greaterThanOrEqualTo($cutoff);
                if ($isOnline) {
                    $online++;
                } elseif ($lastSeen && ($oldest === null || $lastSeen->lessThan($oldest))) {
                    $oldest = $lastSeen;
                }
            }
        }

        return [
            'total' => $total,
            'online' => $online,
            'oldestOfflineSince' => $oldest?->toIso8601String(),
        ];
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
