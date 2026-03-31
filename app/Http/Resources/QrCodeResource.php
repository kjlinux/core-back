<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QrCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => (string) $this->id,
            'companyId'   => (string) $this->company_id,
            'siteId'      => $this->site_id ? (string) $this->site_id : null,
            'siteName'    => $this->when(
                $this->relationLoaded('site'),
                fn() => $this->site?->name
            ),
            'label'       => $this->label,
            'token'       => $this->token,
            'isActive'    => $this->is_active,
            'generatedAt' => $this->generated_at?->toISOString(),
            'expiresAt'   => $this->expires_at?->toISOString(),
            'createdAt'   => $this->created_at?->toISOString(),
        ];
    }
}
