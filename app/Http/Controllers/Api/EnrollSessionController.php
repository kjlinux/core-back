<?php

namespace App\Http\Controllers\Api;

use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Gère les sessions d'enrôlement QR.
 * Flux :
 *   1. Admin crée une session → reçoit un token de session
 *   2. Un QR Code est affiché contenant l'URL /qr-scan?enroll={token}
 *   3. L'employé scanne le QR sur son téléphone → la page /qr-scan soumet son fingerprint
 *   4. L'admin poll GET /enroll-session/{token} jusqu'à recevoir le fingerprint
 *   5. L'enrôlement est confirmé automatiquement
 */
class EnrollSessionController extends BaseApiController
{
    private const TTL_MINUTES = 5;

    /**
     * Crée une session d'enrôlement pour un employé.
     * Retourne un token de session court-vécu (5 min).
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|uuid|exists:employees,id',
        ]);

        $employee = Employee::findOrFail($request->input('employee_id'));

        $sessionToken = Str::uuid()->toString();

        Cache::put('enroll_session:' . $sessionToken, [
            'employee_id'   => $employee->id,
            'employee_name' => $employee->first_name . ' ' . $employee->last_name,
            'status'        => 'pending',   // pending | completed
            'fingerprint'   => null,
            'device_info'   => null,
            'created_at'    => now()->toISOString(),
        ], now()->addMinutes(self::TTL_MINUTES));

        return $this->successResponse([
            'sessionToken' => $sessionToken,
            'employeeId'   => $employee->id,
            'employeeName' => $employee->first_name . ' ' . $employee->last_name,
            'expiresIn'    => self::TTL_MINUTES * 60,
        ]);
    }

    /**
     * Poll : retourne l'état de la session (pending ou completed avec fingerprint).
     */
    public function status(string $token): JsonResponse
    {
        $session = Cache::get('enroll_session:' . $token);

        if (!$session) {
            return $this->errorResponse('Session expiree ou introuvable', 404);
        }

        return $this->successResponse($session);
    }

    /**
     * Soumission depuis le téléphone de l'employé (page /qr-scan?enroll=token).
     * Pas d'auth requise — le token de session tient lieu d'autorisation.
     */
    public function submit(Request $request, string $token): JsonResponse
    {
        $session = Cache::get('enroll_session:' . $token);

        if (!$session) {
            return $this->errorResponse('Session expiree ou introuvable', 404);
        }

        if ($session['status'] === 'completed') {
            return $this->errorResponse('Session deja completee', 409);
        }

        $request->validate([
            'device_fingerprint' => 'required|string|max:64',
            'device_info'        => 'nullable|string|max:255',
        ]);

        $fingerprint = $request->input('device_fingerprint');
        $deviceInfo  = $request->input('device_info');

        // Vérifier que le fingerprint n'est pas déjà utilisé par un autre employé
        $conflict = Employee::where('device_fingerprint', $fingerprint)
            ->where('id', '!=', $session['employee_id'])
            ->exists();

        if ($conflict) {
            return $this->errorResponse('Cet appareil est deja enrole pour un autre employe.', 409);
        }

        // Enrôler directement l'employé
        $employee = Employee::findOrFail($session['employee_id']);
        $employee->update([
            'device_fingerprint' => $fingerprint,
            'device_info'        => $deviceInfo,
            'device_enrolled_at' => now(),
        ]);

        // Mettre à jour la session avec le résultat
        Cache::put('enroll_session:' . $token, array_merge($session, [
            'status'      => 'completed',
            'fingerprint' => $fingerprint,
            'device_info' => $deviceInfo,
        ]), now()->addMinutes(2)); // garder 2 min pour que l'admin récupère le résultat

        return $this->successResponse([
            'enrolled'     => true,
            'employeeName' => $session['employee_name'],
        ], 'Telephone enrole avec succes');
    }
}
