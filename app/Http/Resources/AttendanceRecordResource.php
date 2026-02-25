<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordResource extends JsonResource
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
            'date' => $this->date,
            'entryTime' => $this->entry_time?->toISOString(),
            'exitTime' => $this->exit_time?->toISOString(),
            'status' => $this->status,
            'lateMinutes' => $this->late_minutes,
            'earlyDepartureMinutes' => $this->early_departure_minutes,
            'source' => $this->source,
            'isDoubleBadge' => (bool) $this->is_double_badge,
            'notes' => $this->notes,
        ];
    }
}
