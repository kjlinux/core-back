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
        $user = auth()->user();

        $request->validate([
            'site_id'    => 'required|uuid|exists:sites,id',
            'label'      => 'nullable|string|max:100',
            'company_id' => $user->isSuperAdmin() ? 'required|uuid|exists:companies,id' : 'nullable',
        ]);

        $data = $this->enforceCompanyId(['site_id' => $request->input('site_id')]);

        if ($user->isSuperAdmin()) {
            $data['company_id'] = $request->input('company_id');
        }

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

        $scansToday = (clone $attendanceQuery)->whereDate('date', today())->count();

        $presentToday = (clone $attendanceQuery)->whereDate('date', today())->distinct('employee_id')->count();
        $attendanceRate = $totalEmployees > 0
            ? round(($presentToday / $totalEmployees) * 100)
            : 0;

        $activeQrCodes = (clone $qrQuery)->where('is_active', true)->count();
        $totalQrCodes = (clone $qrQuery)->count();

        return $this->successResponse([
            'totalQrCodes' => $totalQrCodes,
            'activeQrCodes' => $activeQrCodes,
            'enrolledDevices' => $enrolledDevices,
            'totalEmployees' => $totalEmployees,
            'scansToday' => $scansToday,
            'attendanceRate' => $attendanceRate,
        ]);
    }
}
