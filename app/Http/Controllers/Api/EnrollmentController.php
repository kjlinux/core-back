<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Biometric\StoreEnrollmentRequest;
use App\Http\Resources\FingerprintEnrollmentResource;
use App\Models\BiometricAuditLog;
use App\Models\Employee;
use App\Models\FingerprintEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnrollmentController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = FingerprintEnrollment::with('employee');

        if ($request->filled('device_id')) {
            $query->where('device_id', $request->device_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $enrollments = $query->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(FingerprintEnrollmentResource::collection($enrollments));
    }

    public function store(StoreEnrollmentRequest $request): JsonResponse
    {
        $data = $request->validated();
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

        return $this->resourceResponse(new FingerprintEnrollmentResource($enrollment), '', 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $enrollment = FingerprintEnrollment::findOrFail($id);
        $employeeId = $enrollment->employee_id;

        BiometricAuditLog::create([
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'enrollment_deleted',
            'target' => $enrollment->employee_id,
            'details' => 'Enrolement biometrique supprime pour employe ID: ' . $enrollment->employee_id,
        ]);

        $enrollment->delete();

        $remainingEnrollments = FingerprintEnrollment::where('employee_id', $employeeId)->count();
        $employee = Employee::find($employeeId);
        if ($employee) {
            $employee->update(['biometric_enrolled' => $remainingEnrollments > 0]);
        }

        return $this->noContentResponse();
    }
}
