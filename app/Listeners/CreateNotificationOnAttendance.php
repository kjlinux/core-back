<?php

namespace App\Listeners;

use App\Events\AttendanceRecorded;
use App\Events\NotificationReceived;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class CreateNotificationOnAttendance implements ShouldHandleEventsAfterCommit
{
    public function handle(AttendanceRecorded $event): void
    {
        $record = $event->record;
        $employee = $record->employee;
        $sourceName = $record->source === 'rfid' ? 'RFID' : 'Biometrique';

        $statusLabels = [
            'present' => 'Present',
            'late' => 'En retard',
            'left_early' => 'Depart anticipe',
        ];

        $statusLabel = $statusLabels[$record->status] ?? $record->status;
        $action = $record->exit_time ? 'Sortie' : 'Entree';

        $title = "Pointage {$sourceName} - {$action}";
        $message = "{$employee->full_name} - {$statusLabel} ({$sourceName})";

        // Notify all admin/manager users of the same company
        $companyId = $employee->company_id;
        $users = User::where('company_id', $companyId)
            ->whereIn('role', ['admin_enterprise', 'manager'])
            ->get();

        // Also notify super_admins
        $superAdmins = User::where('role', 'super_admin')->get();
        $users = $users->merge($superAdmins);

        foreach ($users as $user) {
            $notification = AppNotification::create([
                'user_id' => $user->id,
                'type' => 'attendance',
                'title' => $title,
                'message' => $message,
                'data' => [
                    'recordId' => (string) $record->id,
                    'employeeId' => (string) $record->employee_id,
                    'source' => $record->source,
                    'status' => $record->status,
                ],
            ]);

            event(new NotificationReceived($notification));
        }
    }
}
