<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AbsenceRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $employee = $this->relationLoaded('employee') ? $this->employee : $this->employee;

        return [
            'id' => (string) $this->id,
            'employeeId' => (string) $this->employee_id,
            'employeeName' => $employee
                ? $employee->first_name . ' ' . $employee->last_name
                : null,
            'companyId' => (string) $this->company_id,
            'dateStart' => $this->date_start?->toDateString(),
            'dateEnd' => $this->date_end?->toDateString(),
            'reason' => $this->reason,
            'justificatifUrl' => $this->justificatif_url,
            'status' => $this->status,
            'reviewedBy' => $this->reviewed_by ? (string) $this->reviewed_by : null,
            'reviewedAt' => $this->reviewed_at?->toISOString(),
            'reviewNote' => $this->review_note,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
