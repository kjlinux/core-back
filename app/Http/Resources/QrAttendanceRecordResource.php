<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QrAttendanceRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => (string) $this->id,
            'employeeId'       => (string) $this->employee_id,
            'employeeName'     => $this->when(
                $this->relationLoaded('employee'),
                fn() => $this->employee
                    ? $this->employee->first_name . ' ' . $this->employee->last_name
                    : null
            ),
            'qrCodeId'         => (string) $this->qr_code_id,
            'companyId'        => (string) $this->company_id,
            'date'             => $this->date?->toDateString(),
            'entryTime'        => $this->entry_time,
            'exitTime'         => $this->exit_time,
            'status'           => $this->status,
            'scannedAt'        => $this->scanned_at?->toISOString(),
            'notes'            => $this->notes,
            'gpsVerified'      => $this->gps_verified,
            'distanceMeters'   => $this->distance_meters,
            'scanLatitude'     => $this->scan_latitude,
            'scanLongitude'    => $this->scan_longitude,
            'createdAt'        => $this->created_at?->toISOString(),
        ];
    }
}
