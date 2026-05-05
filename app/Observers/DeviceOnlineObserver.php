<?php

namespace App\Observers;

use App\Models\DeviceAlert;
use App\Services\AlertService;

class DeviceOnlineObserver
{
    public function __construct(protected AlertService $alerts) {}

    public function updated($device): void
    {
        if (!$device->wasChanged('is_online')) return;
        if (!$device->is_online) return;

        $kind = match (true) {
            $device instanceof \App\Models\RfidDevice => 'rfid',
            $device instanceof \App\Models\BiometricDevice => 'biometric',
            $device instanceof \App\Models\FeelbackDevice => 'feelback',
            default => null,
        };
        if (!$kind) return;

        $this->alerts->resolveByDevice($kind, $device->id, DeviceAlert::TYPE_OFFLINE_THRESHOLD);
    }
}
