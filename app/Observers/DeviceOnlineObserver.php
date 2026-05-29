<?php

namespace App\Observers;

use App\Events\DeviceStatusUpdated;
use App\Models\DeviceAlert;
use App\Services\AlertService;
use Illuminate\Support\Facades\Log;

class DeviceOnlineObserver
{
    public function __construct(protected AlertService $alerts) {}

    public function updated($device): void
    {
        if (!$device->wasChanged('is_online')) return;

        $kind = match (true) {
            $device instanceof \App\Models\RfidDevice => 'rfid',
            $device instanceof \App\Models\BiometricDevice => 'biometric',
            $device instanceof \App\Models\FeelbackDevice => 'feelback',
            default => null,
        };
        if (!$kind) return;

        $previous = $device->getOriginal('is_online') ? 'online' : 'offline';
        $current = $device->is_online ? 'online' : 'offline';

        // Broadcast UNIQUEMENT pour le retour en ligne (offline → online).
        // L'event 'offline' est deja emis par CheckDeviceHealthCommand.
        if ($device->is_online) {
            try {
                event(DeviceStatusUpdated::fromDevice($kind, $device, $current, $previous));
            } catch (\Throwable $e) {
                Log::warning('[DeviceOnlineObserver] broadcast online echoue: ' . $e->getMessage());
            }
            $this->alerts->resolveByDevice($kind, $device->id, DeviceAlert::TYPE_OFFLINE_THRESHOLD);
        }
    }
}
