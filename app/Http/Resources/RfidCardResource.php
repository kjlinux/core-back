<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RfidCardResource extends JsonResource
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
            'uid' => $this->uid,
            'employeeId' => (string) $this->employee_id,
            'employeeName' => $this->when(
                $this->relationLoaded('employee'),
                fn () => $this->employee
                    ? $this->employee->first_name . ' ' . $this->employee->last_name
                    : null
            ),
            'companyId' => (string) $this->company_id,
            'status' => $this->status,
            'assignedAt' => $this->assigned_at?->toISOString(),
            'blockedAt' => $this->blocked_at?->toISOString(),
            'blockReason' => $this->block_reason,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
