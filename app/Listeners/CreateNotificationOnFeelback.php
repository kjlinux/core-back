<?php

namespace App\Listeners;

use App\Events\FeelbackReceived;
use App\Events\NotificationReceived;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class CreateNotificationOnFeelback implements ShouldHandleEventsAfterCommit
{
    public function handle(FeelbackReceived $event): void
    {
        $entry = $event->entry;

        $levelLabels = [
            'bon' => 'Bon',
            'neutre' => 'Neutre',
            'mauvais' => 'Mauvais',
        ];

        $levelLabel = $levelLabels[$entry->level] ?? $entry->level;
        $siteName = $entry->site->name ?? '';

        $title = "Feelback - {$levelLabel}";
        $message = "{$siteName}: {$levelLabel}";

        // Only notify on 'mauvais' feedback to avoid noise
        if ($entry->level !== 'mauvais') {
            return;
        }

        $companyId = $entry->device->company_id ?? null;

        // Cloisonnement multi-tenant : uniquement les admins/managers de l'entreprise du
        // capteur. Les super_admin ne sont pas notifies (sinon flux de toutes les societes).
        $users = collect();
        if ($companyId) {
            $users = User::where('company_id', $companyId)
                ->whereIn('role', ['admin_enterprise', 'manager'])
                ->get();
        }

        foreach ($users as $user) {
            $notification = AppNotification::create([
                'user_id' => $user->id,
                'type' => 'feelback',
                'title' => $title,
                'message' => $message,
                'data' => [
                    'entryId' => (string) $entry->id,
                    'level' => $entry->level,
                    'deviceId' => (string) $entry->device_id,
                    'siteName' => $siteName,
                ],
            ]);

            event(new NotificationReceived($notification));
        }
    }
}
