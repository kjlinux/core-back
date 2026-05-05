<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleResource extends JsonResource
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
            'name' => $this->name,
            'type' => $this->type,
            'startTime' => substr($this->start_time, 0, 5),
            'endTime' => substr($this->end_time, 0, 5),
            'breakStart' => $this->break_start ? substr($this->break_start, 0, 5) : null,
            'breakEnd' => $this->break_end ? substr($this->break_end, 0, 5) : null,
            'workDays' => $this->work_days,
            'lateTolerance' => $this->late_tolerance,
            'assignedDepartments' => $this->assigned_departments,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
