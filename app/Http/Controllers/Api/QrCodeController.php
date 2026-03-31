<?php

namespace App\Http\Controllers\Api;

use App\Models\QrCode;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class QrCodeController extends BaseApiController
{
    /**
     * Liste les QR codes de sites.
     */
    public function index(Request $request): JsonResponse
    {
        $query = QrCode::with('site');

        $this->scopeByCompany($query);

        $query->when($request->input('site_id'), fn($q, $v) => $q->where('site_id', $v));
        $query->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')));

        $qrCodes = $query->orderBy('generated_at', 'desc')->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(\App\Http\Resources\QrCodeResource::collection($qrCodes));
    }

    public function show(string $id): JsonResponse
    {
        $qrCode = QrCode::with('site')->findOrFail($id);

        return $this->resourceResponse(new \App\Http\Resources\QrCodeResource($qrCode));
    }

    /**
     * Génère un QR Code pour un site.
     * Un seul QR actif par site — l'ancien est désactivé.
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'site_id' => 'required|uuid|exists:sites,id',
            'label' => 'nullable|string|max:100',
        ]);

        $data = $this->enforceCompanyId(['site_id' => $request->input('site_id')]);

        // Désactiver l'ancien QR du site
        QrCode::where('site_id', $data['site_id'])->update(['is_active' => false]);

        $qrCode = QrCode::create([
            'company_id' => $data['company_id'],
            'site_id' => $data['site_id'],
            'label' => $request->input('label'),
            'token' => Str::random(48),
            'is_active' => true,
            'generated_at' => now(),
        ]);

        $qrCode->load('site');

        return $this->resourceResponse(new \App\Http\Resources\QrCodeResource($qrCode), 'QR Code de site genere avec succes', 201);
    }

    public function revoke(string $id): JsonResponse
    {
        $qrCode = QrCode::findOrFail($id);
        $qrCode->update(['is_active' => false]);

        return $this->noContentResponse();
    }

    public function stats(): JsonResponse
    {
        $user = auth()->user();

        $attendanceQuery = \App\Models\QrAttendanceRecord::query();
        $qrQuery = QrCode::query();

        if (!$user->isSuperAdmin() && $user->company_id) {
            $attendanceQuery->where('company_id', $user->company_id);
            $qrQuery->where('company_id', $user->company_id);
        }

        $totalEmployees = \App\Models\Employee::when(
            !$user->isSuperAdmin() && $user->company_id,
            fn($q) => $q->where('company_id', $user->company_id)
        )->where('is_active', true)->count();

        $enrolledDevices = \App\Models\Employee::when(
            !$user->isSuperAdmin() && $user->company_id,
            fn($q) => $q->where('company_id', $user->company_id)
        )->whereNotNull('device_fingerprint')->count();

        $scansToday = $attendanceQuery->whereDate('scanned_at', today())->count();

        $attendanceRate = $totalEmployees > 0
            ? round(($attendanceQuery->clone()->whereDate('date', today())->distinct('employee_id')->count() / $totalEmployees) * 100)
            : 0;

        return $this->successResponse([
            'totalQrCodes' => $qrQuery->where('is_active', true)->count(),
            'activeQrCodes' => $qrQuery->clone()->where('is_active', true)->count(),
            'enrolledDevices' => $enrolledDevices,
            'totalEmployees' => $totalEmployees,
            'scansToday' => $scansToday,
            'attendanceRate' => $attendanceRate,
        ]);
    }
}
