<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeelbackDeviceResource extends JsonResource
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
            'siteName' => $this->when(
                $this->relationLoaded('site'),
                fn () => $this->site?->name
            ),
            'isOnline' => (bool) $this->is_online,
            'batteryLevel' => $this->battery_level,
            'lastPingAt' => $this->last_ping_at?->toISOString(),
            'assignedAgent' => $this->assigned_agent,
        ];
    }
}
