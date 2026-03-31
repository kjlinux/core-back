<?php

namespace App\Http\Controllers\Api;

use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeDeviceController extends BaseApiController
{
    /**
     * Enrôle le téléphone d'un employé.
     * Appelé par l'admin/technicien depuis l'interface : l'employé présente son téléphone,
     * l'admin saisit son ID et le fingerprint généré par le navigateur du téléphone.
     */
    public function enroll(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id'        => 'required|uuid|exists:employees,id',
            'device_fingerprint' => 'required|string|max:64',
            'device_info'        => 'nullable|string|max:255',
        ]);

        // Vérifier que le fingerprint n'est pas déjà utilisé par un autre employé
        $conflict = Employee::where('device_fingerprint', $request->input('device_fingerprint'))
            ->where('id', '!=', $request->input('employee_id'))
            ->exists();

        if ($conflict) {
            return $this->errorResponse(
                'Cet appareil est deja enrole pour un autre employe.',
                409
            );
        }

        $employee = Employee::findOrFail($request->input('employee_id'));

        $employee->update([
            'device_fingerprint'  => $request->input('device_fingerprint'),
            'device_info'         => $request->input('device_info'),
            'device_enrolled_at'  => now(),
        ]);

        return $this->successResponse([
            'employeeId'       => $employee->id,
            'employeeName'     => $employee->first_name . ' ' . $employee->last_name,
            'deviceInfo'       => $employee->device_info,
            'deviceEnrolledAt' => $employee->device_enrolled_at?->toISOString(),
        ], 'Appareil enrole avec succes');
    }

    /**
     * Révoque l'enrôlement du téléphone d'un employé.
     */
    public function revoke(Request $request, string $employeeId): JsonResponse
    {
        $employee = Employee::findOrFail($employeeId);

        $employee->update([
            'device_fingerprint' => null,
            'device_info'        => null,
            'device_enrolled_at' => null,
        ]);

        return $this->successResponse(null, 'Appareil revoque avec succes');
    }

    /**
     * Retourne le fingerprint du navigateur actuel — utilisé côté mobile
     * pour que l'employé puisse s'auto-enrôler après validation d'un code OTP.
     * Pour l'instant : simple endpoint de récupération du fingerprint envoyé.
     */
    public function identify(Request $request): JsonResponse
    {
        $request->validate([
            'device_fingerprint' => 'required|string|max:64',
            'device_info'        => 'nullable|string|max:255',
        ]);

        $employee = Employee::where('device_fingerprint', $request->input('device_fingerprint'))
            ->first();

        if (!$employee) {
            return $this->successResponse([
                'enrolled'    => false,
                'employeeId'  => null,
                'employeeName' => null,
            ]);
        }

        return $this->successResponse([
            'enrolled'     => true,
            'employeeId'   => $employee->id,
            'employeeName' => $employee->first_name . ' ' . $employee->last_name,
        ]);
    }
}
