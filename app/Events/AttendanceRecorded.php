<?php

namespace App\Events;

use App\Models\AttendanceRecord;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttendanceRecorded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AttendanceRecord $record)
    {
        $this->record->load('employee');
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('attendance')];
    }

    public function broadcastAs(): string
    {
        return 'attendance.recorded';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => (string) $this->record->id,
            'employeeId' => (string) $this->record->employee_id,
            'employeeName' => $this->record->employee->full_name ?? '',
            'date' => $this->record->date->format('Y-m-d'),
            'entryTime' => $this->record->entry_time?->toISOString(),
            'exitTime' => $this->record->exit_time?->toISOString(),
            'status' => $this->record->status,
            'source' => $this->record->source,
        ];
    }
}
