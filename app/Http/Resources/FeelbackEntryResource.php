<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeelbackEntryResource extends JsonResource
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
            'deviceId' => (string) $this->device_id,
            'level' => $this->level,
            'timestamp' => $this->created_at?->toISOString(),
            'siteId' => (string) $this->site_id,
            'siteName' => $this->when(
                $this->relationLoaded('site'),
                fn () => $this->site?->name
            ),
            'agentId' => (string) $this->agent_id,
            'agentName' => $this->agent_name,
        ];
    }
}
