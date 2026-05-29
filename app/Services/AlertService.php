<?php

namespace App\Services;

use App\Events\DeviceAlertCreated;
use App\Events\DeviceAlertResolved;
use App\Mail\DeviceAlertCreatedMail;
use App\Models\DeviceAlert;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AlertService
{
    public function openOrUpdate(array $attributes): DeviceAlert
    {
        $existing = DeviceAlert::query()
            ->where('device_kind', $attributes['device_kind'])
            ->where('type', $attributes['type'])
            ->when(
                !empty($attributes['device_id']),
                fn ($q) => $q->where('device_id', $attributes['device_id']),
                fn ($q) => $q->whereNull('device_id'),
            )
            ->whereIn('status', [DeviceAlert::STATUS_OPEN, DeviceAlert::STATUS_ACKNOWLEDGED])
            ->first();

        if ($existing) {
            return $existing;
        }

        $alert = DeviceAlert::create(array_merge([
            'severity' => DeviceAlert::SEVERITY_MEDIUM,
            'status' => DeviceAlert::STATUS_OPEN,
        ], $attributes));

        $this->safeBroadcast(fn () => event(new DeviceAlertCreated($alert)));
        $this->notifySupport($alert);

        return $alert;
    }

    public function resolve(DeviceAlert $alert): DeviceAlert
    {
        if ($alert->status === DeviceAlert::STATUS_RESOLVED) {
            return $alert;
        }
        $alert->update([
            'status' => DeviceAlert::STATUS_RESOLVED,
            'resolved_at' => now(),
        ]);
        $this->safeBroadcast(fn () => event(new DeviceAlertResolved($alert)));
        return $alert;
    }

    protected function safeBroadcast(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::warning('[AlertService] broadcast échoué (serveur temps réel injoignable ?): ' . $e->getMessage());
        }
    }

    public function resolveByDevice(string $deviceKind, string $deviceId, ?string $type = null): void
    {
        DeviceAlert::query()
            ->where('device_kind', $deviceKind)
            ->where('device_id', $deviceId)
            ->when($type, fn ($q, $t) => $q->where('type', $t))
            ->whereIn('status', [DeviceAlert::STATUS_OPEN, DeviceAlert::STATUS_ACKNOWLEDGED])
            ->get()
            ->each(fn (DeviceAlert $a) => $this->resolve($a));
    }

    protected function notifySupport(DeviceAlert $alert): void
    {
        try {
            $recipients = User::query()
                ->where('role', 'support_it')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->pluck('email')
                ->all();

            if (empty($recipients)) {
                return;
            }

            Mail::to($recipients)->queue(new DeviceAlertCreatedMail($alert));
            $alert->update(['notified_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('[AlertService] notify support failed: ' . $e->getMessage(), [
                'alert_id' => $alert->id,
            ]);
        }
    }
}
