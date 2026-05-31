<?php

namespace App\Services;

use App\Events\NotificationReceived;
use App\Mail\EmployeeNotificationMail;
use App\Models\AppNotification;
use App\Models\Employee;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmployeeNotificationService
{
    /**
     * Crée une notification destinée au compte utilisateur lié à un employé,
     * et envoie optionnellement un email à l'employé.
     *
     * @param  array<string, mixed>  $data
     */
    public function notifyEmployee(
        Employee $employee,
        string $type,
        string $title,
        string $message,
        array $data = [],
        bool $sendEmail = false,
        bool $broadcast = true,
    ): void {
        $user = $employee->user;

        if ($user) {
            $notification = AppNotification::create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data,
            ]);

            if ($broadcast) {
                event(new NotificationReceived($notification));
            }
        }

        if ($sendEmail && $employee->email) {
            try {
                Mail::to($employee->email)->queue(new EmployeeNotificationMail($employee, $title, $message));
            } catch (\Throwable $e) {
                Log::error('EmployeeNotificationMail failed for employee '.$employee->id.': '.$e->getMessage());
            }
        }
    }
}
