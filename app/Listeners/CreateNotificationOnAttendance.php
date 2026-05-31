<?php

namespace App\Listeners;

use App\Events\AttendanceRecorded;
use App\Events\NotificationReceived;
use App\Models\AppNotification;
use App\Models\User;
use App\Services\EmployeeNotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class CreateNotificationOnAttendance implements ShouldHandleEventsAfterCommit
{
    public function __construct(private EmployeeNotificationService $employeeNotifications) {}

    public function handle(AttendanceRecorded $event): void
    {
        $record = $event->record;
        $employee = $record->employee;
        $sourceName = $record->source === 'rfid' ? 'RFID' : 'Biométrique';

        $statusLabels = [
            'present' => 'Présent',
            'late' => 'En retard',
            'left_early' => 'Départ anticipé',
        ];

        $statusLabel = $statusLabels[$record->status] ?? $record->status;
        $action = $record->exit_time ? 'Sortie' : 'Entrée';

        $title = "Pointage {$sourceName} - {$action}";
        $message = "{$employee->full_name} - {$statusLabel} ({$sourceName})";

        // Notifier uniquement les admins/managers de la MEME entreprise (cloisonnement
        // multi-tenant). Les super_admin ne sont volontairement pas notifies de chaque
        // pointage : n'etant rattaches a aucune entreprise, ils recevraient le flux de
        // toutes les societes (fuite d'information + spam).
        $companyId = $employee->company_id;
        $users = User::where('company_id', $companyId)
            ->whereIn('role', ['admin_enterprise', 'manager'])
            ->get();

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

        // Notifier l'employé concerné de son propre pointage (in-app uniquement).
        $action = $record->exit_time ? 'Sortie' : 'Entrée';
        $time = ($record->exit_time ?? $record->entry_time)?->format('H:i');

        $this->employeeNotifications->notifyEmployee(
            $employee,
            'attendance',
            "Pointage enregistré - {$action}",
            "Votre pointage ({$action}) a été enregistré".($time ? " à {$time}" : '')." via {$sourceName}.",
            [
                'recordId' => (string) $record->id,
                'source' => $record->source,
                'status' => $record->status,
            ],
        );
    }
}
