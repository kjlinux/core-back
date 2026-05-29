<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'companyId' => (string) $this->company_id,
            'name' => $this->name,
            'type' => $this->type,
            'defaultLateTolerance' => (int) ($this->default_late_tolerance ?? $this->late_tolerance ?? 0),
            'days' => $this->days ?? [],
            'assignedDepartments' => $this->assigned_departments ?? [],
            'createdAt' => $this->created_at?->toISOString(),
            // Champs legacy conserves pour retro-compatibilite
            'startTime' => $this->start_time ? substr($this->start_time, 0, 5) : null,
            'endTime' => $this->end_time ? substr($this->end_time, 0, 5) : null,
            'breakStart' => $this->break_start ? substr($this->break_start, 0, 5) : null,
            'breakEnd' => $this->break_end ? substr($this->break_end, 0, 5) : null,
            'workDays' => $this->work_days,
            'lateTolerance' => $this->late_tolerance,
        ];
    }
}
