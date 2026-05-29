<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OtaUpdateLogResource extends JsonResource
{
    /** Cache des noms de terminaux par requete HTTP pour eviter le N+1 sur les collections. */
    private static array $deviceNameCache = ['rfid' => [], 'biometric' => []];

    public function toArray(Request $request): array
    {
        $deviceName = null;
        $kind = $this->device_kind;
        if (in_array($kind, ['rfid', 'biometric'], true)) {
            if (!array_key_exists($this->device_id, self::$deviceNameCache[$kind])) {
                $model = $kind === 'rfid' ? \App\Models\RfidDevice::class : \App\Models\BiometricDevice::class;
                self::$deviceNameCache[$kind][$this->device_id] = $model::whereKey($this->device_id)->value('name');
            }
            $deviceName = self::$deviceNameCache[$kind][$this->device_id];
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
