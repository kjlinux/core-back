<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FingerprintEnrollmentResource extends JsonResource
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
            'employeeId' => (string) $this->employee_id,
            'employeeName' => $this->when(
                $this->relationLoaded('employee'),
                fn () => $this->employee
                    ? $this->employee->first_name . ' ' . $this->employee->last_name
                    : null
            ),
            'deviceId' => (string) $this->device_id,
            'status' => $this->status,
            'enrolledAt' => $this->enrolled_at?->toISOString(),
            'templateHash' => $this->template_hash,
        ];
    }
}
