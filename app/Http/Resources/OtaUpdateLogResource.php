<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OtaUpdateLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $deviceName = null;
        if ($this->device_kind === 'rfid') {
            $deviceName = \App\Models\RfidDevice::find($this->device_id)?->name;
        } elseif ($this->device_kind === 'biometric') {
            $deviceName = \App\Models\BiometricDevice::find($this->device_id)?->name;
        }

        return [
            'id' => (string) $this->id,
            'deviceId' => (string) $this->device_id,
            'deviceName' => $deviceName,
            'deviceKind' => $this->device_kind,
            'firmwareVersionId' => (string) $this->firmware_version_id,
            'firmwareVersion' => $this->when(
                $this->relationLoaded('firmwareVersion'),
                fn() => $this->firmwareVersion?->version
            ),
            'status' => $this->status,
            'triggeredBy' => $this->triggered_by,
            'startedAt' => $this->started_at?->toISOString(),
            'completedAt' => $this->completed_at?->toISOString(),
            'errorMessage' => $this->error_message,
        ];
    }
}
