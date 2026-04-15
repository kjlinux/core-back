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
    public function index(Request $request): JsonResponse
    {
        $query = FingerprintEnrollment::with('employee');

        $user = $request->user();
        if (!$user->isSuperAdmin()) {
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
            'details' => 'Enrolement biometrique cree pour employe: ' . ($employee ? $employee->full_name : $enrollment->employee_id),
        ]);

        TechnicienActivityLog::record('enroll', 'biometric_enrollment', (string) $enrollment->id, $employee ? $employee->first_name . ' ' . $employee->last_name : null);

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

        if (!$device->mqtt_topic) {
            return $this->errorResponse('Le terminal n\'a pas de topic MQTT configure', 422);
        }

        if (!$device->is_online) {
            return $this->errorResponse('Le terminal est hors ligne', 422);
        }

        $enrollment = FingerprintEnrollment::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'status' => 'pending',
            'template_hash' => '',
        ]);

        $responseTopic = $mqtt->getResponseTopic($device->mqtt_topic);
        $commandCode = config('mqtt.command_codes.biometric.ENROLE', 'ENROLE');

        $payload = json_encode([
            'command' => $commandCode,
            'device_id' => $device->id,
            'device_type' => 'biometric',
            'enrollment_id' => $enrollment->id,
            'employee_id' => $employee->id,
            'timestamp' => now()->toISOString(),
        ]);

        try {
            $mqtt->publish($responseTopic, $payload);

            BiometricAuditLog::create([
                'user_id' => $request->user()->id,
                'user_name' => $request->user()->name,
                'action' => 'enrollment_started',
                'target' => $employee->full_name,
                'details' => 'Commande ENROLE envoyee au terminal ' . $device->serial_number . ' pour ' . $employee->full_name,
            ]);

            return $this->resourceResponse(
                new FingerprintEnrollmentResource($enrollment->load('employee')),
                'Commande d\'enrolement envoyee au terminal',
                201
            );
        } catch (\Exception $e) {
            $enrollment->update(['status' => 'failed']);

            return $this->errorResponse('Echec d\'envoi de la commande ENROLE: ' . $e->getMessage(), 500);
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
            'details' => 'Enrolement biometrique supprime pour employe ID: ' . $enrollment->employee_id,
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
}
