<?php

namespace App\Listeners;

use App\Events\DeviceAlertCreated;
use App\Events\NotificationReceived;
use App\Models\AppNotification;
use App\Models\DeviceAlert;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Log;

/**
 * Cree une notification persistee (cloche + son) pour chaque
 * super_admin / support_it / admin_enterprise de la company concernee
 * lorsqu'un capteur tombe hors ligne.
 *
 * Le retour en ligne ne genere PAS de notification (anti-bruit) - juste
 * un toast cote frontend.
 */
class NotifyUsersOnDeviceOffline implements ShouldHandleEventsAfterCommit
{
    public function handle(DeviceAlertCreated $event): void
    {
        $alert = $event->alert;

        if ($alert->type !== DeviceAlert::TYPE_OFFLINE_THRESHOLD) {
            return;
        }

        $companyId = $alert->company_id;

        $recipients = User::query()
            ->where('is_active', true)
            ->where(function ($q) use ($companyId) {
                $q->whereIn('role', ['super_admin', 'support_it']);
                if ($companyId) {
                    $q->orWhere(function ($q2) use ($companyId) {
                        $q2->where('role', 'admin_enterprise')->where('company_id', $companyId);
                    });
                }
            })
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $context = is_array($alert->context) ? $alert->context : [];
        $data = [
            'alertId' => (string) $alert->id,
            'deviceKind' => $alert->device_kind,
            'deviceId' => $alert->device_id ? (string) $alert->device_id : null,
            'severity' => $alert->severity,
            'serialNumber' => $context['serial_number'] ?? null,
            'isWitness' => (bool) ($context['is_witness'] ?? false),
        ];

        foreach ($recipients as $user) {
            try {
                $notification = AppNotification::create([
                    'user_id' => $user->id,
                    'type' => 'device.offline',
                    'title' => 'Capteur hors ligne',
                    'message' => $alert->title,
                    'data' => $data,
                ]);

                event(new NotificationReceived($notification));
            } catch (\Throwable $e) {
                Log::warning('[NotifyUsersOnDeviceOffline] echec pour user ' . $user->id . ' : ' . $e->getMessage());
            }
        }
    }
}
