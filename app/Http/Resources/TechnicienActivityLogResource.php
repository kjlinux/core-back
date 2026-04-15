<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TechnicienActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => (string) $this->id,
            'action'        => $this->action,
            'resourceType'  => $this->resource_type,
            'resourceId'    => $this->resource_id ? (string) $this->resource_id : null,
            'resourceLabel' => $this->resource_label,
            'metadata'      => $this->metadata,
            'createdAt'     => $this->created_at?->toISOString(),
            'technicien'    => $this->whenLoaded('technicien', fn() => [
                'id'       => (string) $this->technicien->id,
                'fullName' => trim($this->technicien->first_name . ' ' . $this->technicien->last_name),
                'email'    => $this->technicien->email,
            ]),
            'company' => $this->whenLoaded('company', fn() => [
                'id'   => (string) $this->company->id,
                'name' => $this->company->name,
            ]),
        ];
    }
}
