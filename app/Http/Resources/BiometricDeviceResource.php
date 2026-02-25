<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BiometricDeviceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'serialNumber' => $this->serial_number,
            'companyId' => (string) $this->company_id,
            'siteId' => (string) $this->site_id,
            'name' => $this->name,
            'isOnline' => (bool) $this->is_online,
            'lastSyncAt' => $this->last_sync_at?->toISOString(),
            'firmwareVersion' => $this->firmware_version,
            'enrolledCount' => $this->enrolled_count,
        ];
    }
}
