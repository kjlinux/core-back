<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeelbackAlertResource extends JsonResource
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
            'siteId' => (string) $this->site_id,
            'siteName' => $this->when(
                $this->relationLoaded('site'),
                fn () => $this->site?->name
            ),
            'type' => $this->type,
            'message' => $this->message,
            'threshold' => $this->threshold,
            'currentValue' => $this->current_value,
            'isRead' => (bool) $this->is_read,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
