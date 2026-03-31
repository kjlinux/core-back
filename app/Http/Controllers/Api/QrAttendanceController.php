<?php

namespace App\Http\Controllers\Api;

use App\Models\QrCode;
use App\Models\QrAttendanceRecord;
use App\Models\Employee;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QrAttendanceController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = QrAttendanceRecord::with('employee');

        $this->scopeByCompany($query);

        $query->when($request->input('date'), fn($q, $v) => $q->whereDate('date', $v));
        $query->when($request->input('employee_id'), fn($q, $v) => $q->where('employee_id', $v));
        $query->when($request->input('status'), fn($q, $v) => $q->where('status', $v));
        $query->when($request->input('gps_verified'), fn($q, $v) => $q->where('gps_verified', filter_var($v, FILTER_VALIDATE_BOOLEAN)));

        $records = $query->orderBy('scanned_at', 'desc')->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(\App\Http\Resources\QrAttendanceRecordResource::collection($records));
    }

    /**
     * Scan du QR Code de site depuis le téléphone de l'employé.
     *
     * Flux :
     * 1. Le token identifie le site (QR affiché à l'entrée)
     * 2. Le device_fingerprint identifie l'employé (enrôlé à l'avance)
     * 3. Le GPS vérifie que l'employé est physiquement sur le site
     */
    public function scan(Request $request): JsonResponse
    {
        $request->validate([
            'token'              => 'required|string',
            'device_fingerprint' => 'required|string|max:64',
            'latitude'           => 'nullable|numeric|between:-90,90',
            'longitude'          => 'nullable|between:-180,180',
        ]);

        // 1. Vérifier le QR Code du site
        $qrCode = QrCode::where('token', $request->input('token'))
            ->where('is_active', true)
            ->with('site')
            ->first();

        if (!$qrCode) {
            return $this->errorResponse('QR Code invalide ou inactif', 404);
        }

        // 2. Identifier l'employé par son device_fingerprint
        $employee = Employee::where('device_fingerprint', $request->input('device_fingerprint'))
            ->where('company_id', $qrCode->company_id)
            ->where('is_active', true)
            ->first();

        if (!$employee) {
            return $this->errorResponse(
                'Appareil non reconnu. Veuillez enroler votre telephone aupres de votre responsable.',
                403
            );
        }

        // 3. Vérification GPS si le site a des coordonnées configurées
        $site = $qrCode->site;
        $gpsVerified = false;
        $distanceMeters = null;

        if ($site && $site->latitude && $site->longitude) {
            $lat = $request->input('latitude');
            $lng = $request->input('longitude');

            if ($lat === null || $lng === null) {
                return $this->errorResponse(
                    'La localisation GPS est requise pour pointer sur ce site. Autorisez la localisation dans votre navigateur.',
                    422
                );
            }

            $distanceMeters = $this->haversineDistance(
                $site->latitude, $site->longitude,
                (float) $lat, (float) $lng
            );

            $radius = $site->geofence_radius ?? 100;

            if ($distanceMeters > $radius) {
                return $this->errorResponse(
                    "Vous etes trop loin du site pour pointer ({$distanceMeters}m, rayon autorise : {$radius}m). Vous devez etre present sur le lieu de travail.",
                    403
                );
            }

            $gpsVerified = true;
        }

        // 4. Enregistrer l'entrée ou la sortie
        $existing = QrAttendanceRecord::where('employee_id', $employee->id)
            ->whereDate('date', today())
            ->latest()
            ->first();

        if ($existing && !$existing->exit_time) {
            $exitTime = now();
            $exitStatus = $existing->status;

            $schedule = Schedule::where('company_id', $qrCode->company_id)
                ->whereJsonContains('assigned_departments', $employee->department_id)
                ->first();

            if ($schedule) {
                $endTime = Carbon::parse(today()->toDateString().' '.$schedule->end_time);
                if ($exitTime->lt($endTime)) {
                    $exitStatus = 'left_early';
                }
            }

            $existing->update([
                'exit_time'        => $exitTime->format('H:i:s'),
                'status'           => $exitStatus,
                'scanned_at'       => $exitTime,
                'scan_latitude'    => $request->input('latitude'),
                'scan_longitude'   => $request->input('longitude'),
                'gps_verified'     => $gpsVerified,
                'distance_meters'  => $distanceMeters,
            ]);
            $existing->load('employee');
            return $this->resourceResponse(
                new \App\Http\Resources\QrAttendanceRecordResource($existing),
                'Sortie enregistree'
            );
        }

        // Déterminer le statut d'entrée selon l'horaire du département de l'employé
        $entryTime = now();
        $status = 'present';

        $schedule = Schedule::where('company_id', $qrCode->company_id)
            ->whereJsonContains('assigned_departments', $employee->department_id)
            ->first();

        if ($schedule) {
            $startTime = Carbon::parse(today()->toDateString().' '.$schedule->start_time);
            $tolerance = $schedule->late_tolerance ?? 0;
            if ($entryTime->gt($startTime->copy()->addMinutes($tolerance))) {
                $status = 'late';
            }
        }

        $record = QrAttendanceRecord::create([
            'employee_id'        => $employee->id,
            'qr_code_id'         => $qrCode->id,
            'company_id'         => $qrCode->company_id,
            'date'               => today(),
            'entry_time'         => $entryTime->format('H:i:s'),
            'status'             => $status,
            'scanned_at'         => $entryTime,
            'device_fingerprint' => $request->input('device_fingerprint'),
            'scan_latitude'      => $request->input('latitude'),
            'scan_longitude'     => $request->input('longitude'),
            'gps_verified'       => $gpsVerified,
            'distance_meters'    => $distanceMeters,
        ]);

        $record->load('employee');

        return $this->resourceResponse(
            new \App\Http\Resources\QrAttendanceRecordResource($record),
            'Entree enregistree',
            201
        );
    }

    /**
     * Calcule la distance en mètres entre deux coordonnées GPS (formule de Haversine).
     */
    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): int
    {
        $earthRadius = 6371000; // mètres

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return (int) round($earthRadius * $c);
    }
}
