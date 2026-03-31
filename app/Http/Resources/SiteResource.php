<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => (string) $this->id,
            'companyId'      => (string) $this->company_id,
            'name'           => $this->name,
            'address'        => $this->address,
            'latitude'       => $this->latitude,
            'longitude'      => $this->longitude,
            'geofenceRadius' => $this->geofence_radius,
            'departments'    => DepartmentResource::collection($this->whenLoaded('departments')),
        ];
    }
}
