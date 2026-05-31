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
                ? $employee->first_name.' '.$employee->last_name
                : null,
            'employeeAvatar' => $employee?->avatar,
            'companyId' => (string) $this->company_id,
            'dateStart' => $this->date_start?->toDateString(),
            'dateEnd' => $this->date_end?->toDateString(),
            'reason' => $this->reason,
            'justificatifUrl' => $this->absoluteJustificatifUrl(),
            'status' => $this->status,
            'reviewedBy' => $this->reviewed_by ? (string) $this->reviewed_by : null,
            'reviewedAt' => $this->reviewed_at?->toISOString(),
            'reviewNote' => $this->review_note,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }

    private function absoluteJustificatifUrl(): ?string
    {
        $url = $this->justificatif_url;

        if (! $url) {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return rtrim(config('app.url'), '/').'/'.ltrim($url, '/');
    }
}
