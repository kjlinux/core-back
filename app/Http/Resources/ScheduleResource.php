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
            'startTime' => $this->start_time,
            'endTime' => $this->end_time,
            'breakStart' => $this->break_start,
            'breakEnd' => $this->break_end,
            'workDays' => $this->work_days,
            'lateTolerance' => $this->late_tolerance,
            'assignedDepartments' => $this->assigned_departments,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
