<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Biometric\StoreEnrollmentRequest;
use App\Http\Resources\FingerprintEnrollmentResource;
use App\Models\BiometricAuditLog;
use App\Models\BiometricDevice;
use App\Models\Employee;
use App\Models\FingerprintEnrollment;
use App\Models\TechnicienActivityLog;
use App\Services\MqttService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnrollmentController extends BaseApiController
{
    /**
     * Capacite max de la flash AS608 (doit matcher FP_MAX_TEMPLATES cote firmware).
     */
    private const FP_MAX_TEMPLATES = 162;

    public function index(Request $request): JsonResponse
    {
        $query = FingerprintEnrollment::with('employee');

        $user = $request->user();
        if (! $user->isSuperAdmin()) {
            $activeCompanyId = $this->resolveActiveCompanyId();
            $query->whereHas('employee', function ($q) use ($activeCompanyId) {
                $q->where('company_id', $activeCompanyId);
            });
        }

        $query->when($request->input('search'), function ($q, $search) {
            $q->whereHas('employee', function ($eq) use ($search) {
                $eq->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
            });
        });

        $query->when($request->filled('device_id'), function ($q) use ($request) {
            $q->where('device_id', $request->device_id);
        });

        $query->when($request->filled('status'), function ($q) use ($request) {
            $q->where('status', $request->status);
        });

        $perPage = (int) $request->input('per_page', 15);

        return $this->paginatedResponse(FingerprintEnrollmentResource::collection($query->orderByDesc('created_at')->paginate($perPage)));
    }

    public function show(string $id): JsonResponse
    {
        $enrollment = FingerprintEnrollment::with('employee')->findOrFail($id);

        return $this->resourceResponse(new FingerprintEnrollmentResource($enrollment));
    }

    public function store(StoreEnrollmentRequest $request): JsonResponse
    {
        $data = $this->enforceCompanyId($request->validated());
        $data['status'] = 'pending';

        $device = BiometricDevice::findOrFail($data['device_id']);

        if (! $device->is_online) {
            return $this->errorResponse('Le terminal est hors ligne', 422);
        }

        $enrollment = FingerprintEnrollment::create($data);

        $employee = Employee::find($enrollment->employee_id);

        BiometricAuditLog::create([
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'enrollment_created',
            'target' => $employee ? $employee->full_name : $enrollment->employee_id,
            'details' => 'Enrôlement biométrique créé pour employé : '.($employee ? $employee->full_name : $enrollment->employee_id),
        ]);

        TechnicienActivityLog::record('enroll', 'biometric_enrollment', (string) $enrollment->id, $employee ? $employee->first_name.' '.$employee->last_name : null);

        return $this->resourceResponse(new FingerprintEnrollmentResource($enrollment), '', 201);
    }

    public function enroll(Request $request, MqttService $mqtt): JsonResponse
    {
        $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'device_id' => ['required', 'exists:biometric_devices,id'],
        ]);

        $device = BiometricDevice::findOrFail($request->device_id);
        $employee = Employee::findOrFail($request->employee_id);

        if (! $device->mqtt_topic) {
            return $this->errorResponse('Le terminal n\'a pas de topic MQTT configuré', 422);
        }

        if (! $device->is_online) {
            return $this->errorResponse('Le terminal est hors ligne', 422);
        }

        // Réutiliser un enrollment pending existant pour éviter l'accumulation de slots bloqués
        // quand l'utilisateur clique "Réessayer" avant que le firmware ait répondu.
        $existingPending = FingerprintEnrollment::query()
            ->where('employee_id', $employee->id)
            ->where('device_id', $device->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($existingPending && preg_match('/^FP(\d{1,4})$/', (string) $existingPending->template_hash, $m)) {
            $enrollment = $existingPending;
            $fingerId = (int) $m[1];

            // Invalider les éventuels autres pending en doublon pour ce (employee, device).
            FingerprintEnrollment::query()
                ->where('employee_id', $employee->id)
                ->where('device_id', $device->id)
                ->where('status', 'pending')
                ->where('id', '!=', $enrollment->id)
                ->update(['status' => 'failed']);
        } else {
            // Retry en cas de course entre deux requetes concurrentes : l'index unique
            // (device_id, template_hash) fait echouer la 2e insertion, on realloue.
            $enrollment = null;
            $fingerId = null;
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $fingerId = $this->allocateFingerId($device->id);
                if ($fingerId === null) {
                    return $this->errorResponse('Capacite du terminal atteinte (162 empreintes max)', 422);
                }

                try {
                    $enrollment = FingerprintEnrollment::create([
                        'employee_id' => $employee->id,
                        'device_id' => $device->id,
                        'status' => 'pending',
                        'template_hash' => sprintf('FP%04d', $fingerId),
                    ]);
                    break;
                } catch (\Illuminate\Database\QueryException $e) {
                    // 23000 = MySQL integrity constraint / 23505 = PostgreSQL unique violation.
                    if (! in_array($e->getCode(), ['23000', '23505'], true)) {
                        throw $e;
                    }
                    // Duplicate key : un autre enrollment a pris le slot, on retry.
                }
            }

            if ($enrollment === null) {
                return $this->errorResponse('Impossible d\'allouer un slot AS608 apres plusieurs tentatives', 500);
            }
        }

        $responseTopic = $mqtt->getResponseTopic($device->mqtt_topic);
        $commandCode = config('mqtt.command_codes.biometric.ENROLE', 'ENROLE');

        $payload = json_encode([
            'command' => $commandCode,
            'device_id' => $device->id,
            'device_type' => 'biometric',
            'enrollment_id' => $enrollment->id,
            'employee_id' => $employee->id,
            'finger_id' => $fingerId,
            'timestamp' => now()->toISOString(),
        ]);

        try {
            $mqtt->publish($responseTopic, $payload);

            BiometricAuditLog::create([
                'user_id' => $request->user()->id,
                'user_name' => $request->user()->name,
                'action' => 'enrollment_started',
                'target' => $employee->full_name,
                'details' => 'Commande ENROLE envoyée au terminal '.$device->serial_number.' pour '.$employee->full_name,
            ]);

            return $this->resourceResponse(
                new FingerprintEnrollmentResource($enrollment->load('employee')),
                'Commande d\'enrôlement envoyée au terminal',
                201
            );
        } catch (\Exception $e) {
            $enrollment->update(['status' => 'failed']);

            return $this->errorResponse('Échec d\'envoi de la commande ENROLE : '.$e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, string $id, MqttService $mqtt): JsonResponse
    {
        $enrollment = FingerprintEnrollment::findOrFail($id);
        $employeeId = $enrollment->employee_id;
        $wasEnrolled = $enrollment->status === 'enrolled';
        $deviceId = $enrollment->device_id;

        // Libérer le slot dans la flash du capteur AS608 AVANT de supprimer la ligne DB.
        // Sans ce DELETE, le template reste stocké côté terminal alors que le slot est
        // réattribué côté DB : le firmware refuse alors tout nouvel enrôlement sur ce slot
        // avec « Slot AS608 deja utilise ». Best-effort : un échec broker n'empêche pas
        // la suppression DB.
        $this->sendDeleteToDevice($mqtt, $enrollment);

        BiometricAuditLog::create([
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'enrollment_deleted',
            'target' => $enrollment->employee_id,
            'details' => 'Enrôlement biométrique supprimé pour employé ID : '.$enrollment->employee_id,
        ]);

        $enrollment->delete();

        // enrolled_count est un alias withCount (pas une colonne DB), pas de decrement direct.
        // On met juste a jour le flag biometric_enrolled de l'employe ci-dessous.

        $remainingEnrollments = FingerprintEnrollment::where('employee_id', $employeeId)->count();
        $employee = Employee::find($employeeId);
        if ($employee) {
            $employee->update(['biometric_enrolled' => $remainingEnrollments > 0]);
        }

        return $this->noContentResponse();
    }

    /**
     * Publie la commande DELETE (0x2000B0) au terminal pour effacer le template
     * du slot AS608 correspondant a l'enrollment. Best-effort : toute erreur
     * (broker indisponible, topic manquant, hash non parseable) est ignoree pour
     * ne pas bloquer la suppression cote application.
     */
    private function sendDeleteToDevice(MqttService $mqtt, FingerprintEnrollment $enrollment): void
    {
        if (! preg_match('/^FP(\d{1,4})$/', (string) $enrollment->template_hash, $m)) {
            return;
        }

        $device = BiometricDevice::find($enrollment->device_id);
        if (! $device || ! $device->mqtt_topic) {
            return;
        }

        $payload = json_encode([
            'command' => config('mqtt.command_codes.biometric.DELETE', 'DELETE'),
            'device_id' => $device->id,
            'device_type' => 'biometric',
            'enrollment_id' => $enrollment->id,
            'finger_id' => (int) $m[1],
            'timestamp' => now()->toISOString(),
        ]);

        try {
            $mqtt->publish($mqtt->getResponseTopic($device->mqtt_topic), $payload);
        } catch (\Exception $e) {
            report($e);
        }
    }

    /**
     * Calcule le plus petit slot AS608 libre (1..FP_MAX_TEMPLATES) pour un device.
     * Considere comme occupes TOUS les enrollments existants (y compris failed),
     * car l'index unique (device_id, template_hash) couvre tous les statuts.
     * Retourne null si la capacite est atteinte.
     */
    private function allocateFingerId(string $deviceId): ?int
    {
        $usedHashes = FingerprintEnrollment::query()
            ->where('device_id', $deviceId)
            ->pluck('template_hash')
            ->all();

        $usedSlots = [];
        foreach ($usedHashes as $hash) {
            if (preg_match('/^FP(\d{1,4})$/', (string) $hash, $m)) {
                $usedSlots[(int) $m[1]] = true;
            }
        }

        for ($slot = 1; $slot <= self::FP_MAX_TEMPLATES; $slot++) {
            if (! isset($usedSlots[$slot])) {
                return $slot;
            }
        }

        return null;
    }
}
