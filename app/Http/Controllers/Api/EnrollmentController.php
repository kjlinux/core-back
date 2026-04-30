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

        if ($request->filled('device_id')) {
            $query->where('device_id', $request->device_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $enrollments = $query->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(FingerprintEnrollmentResource::collection($enrollments));
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

        $enrollment = FingerprintEnrollment::create($data);

        $employee = Employee::find($enrollment->employee_id);
        if ($employee) {
            $employee->update(['biometric_enrolled' => true]);
        }

        BiometricAuditLog::create([
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'enrollment_created',
            'target' => $employee ? $employee->full_name : $enrollment->employee_id,
            'details' => 'Enrolement biometrique cree pour employe: '.($employee ? $employee->full_name : $enrollment->employee_id),
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
            return $this->errorResponse('Le terminal n\'a pas de topic MQTT configure', 422);
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
                'details' => 'Commande ENROLE envoyee au terminal '.$device->serial_number.' pour '.$employee->full_name,
            ]);

            return $this->resourceResponse(
                new FingerprintEnrollmentResource($enrollment->load('employee')),
                'Commande d\'enrolement envoyee au terminal',
                201
            );
        } catch (\Exception $e) {
            $enrollment->update(['status' => 'failed']);

            return $this->errorResponse('Echec d\'envoi de la commande ENROLE: '.$e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $enrollment = FingerprintEnrollment::findOrFail($id);
        $employeeId = $enrollment->employee_id;
        $wasEnrolled = $enrollment->status === 'enrolled';
        $deviceId = $enrollment->device_id;

        BiometricAuditLog::create([
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'enrollment_deleted',
            'target' => $enrollment->employee_id,
            'details' => 'Enrolement biometrique supprime pour employe ID: '.$enrollment->employee_id,
        ]);

        $enrollment->delete();

        if ($wasEnrolled) {
            BiometricDevice::where('id', $deviceId)->decrement('enrolled_count');
        }

        $remainingEnrollments = FingerprintEnrollment::where('employee_id', $employeeId)->count();
        $employee = Employee::find($employeeId);
        if ($employee) {
            $employee->update(['biometric_enrolled' => $remainingEnrollments > 0]);
        }

        return $this->noContentResponse();
    }

    /**
     * Calcule le plus petit slot AS608 libre (1..FP_MAX_TEMPLATES) pour un device.
     * Considere comme occupes tous les enrollments non-failed (pending + enrolled)
     * pour eviter une course entre deux creations simultanees.
     * Retourne null si la capacite est atteinte.
     */
    private function allocateFingerId(string $deviceId): ?int
    {
        $usedHashes = FingerprintEnrollment::query()
            ->where('device_id', $deviceId)
            ->whereIn('status', ['pending', 'enrolled'])
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
