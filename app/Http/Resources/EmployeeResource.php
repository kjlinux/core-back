<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'companyId' => (string) $this->company_id,
            'siteId' => (string) $this->site_id,
            'departmentId' => (string) $this->department_id,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'position' => $this->position,
            'employeeNumber' => $this->employee_number,
            'avatar' => $this->avatar,
            'isActive' => (bool) $this->is_active,
            'hireDate' => $this->hire_date?->toDateString(),
            'rfidCardId' => $this->when(
                $this->relationLoaded('rfidCard'),
                fn () => $this->rfidCard ? (string) $this->rfidCard->id : null
            ),
            'biometricEnrolled' => (bool) $this->biometric_enrolled,
            'deviceFingerprint' => $this->device_fingerprint,
            'deviceInfo' => $this->device_info,
            'deviceEnrolledAt' => $this->device_enrolled_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
