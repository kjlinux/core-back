<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
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
            'name' => $this->name,
            'logo' => $this->logo,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'matriculePrefix' => $this->matricule_prefix,
            'isActive' => (bool) $this->is_active,
            'subscription' => $this->subscription,
            'subscriptionStartsAt' => $this->subscription_starts_at?->toISOString(),
            'subscriptionExpiresAt' => $this->subscription_expires_at?->toISOString(),
            'subscriptionNextPeriodPaid' => (bool) $this->subscription_next_period_paid,
            'subscriptionNextExpiresAt' => $this->subscription_next_expires_at?->toISOString(),
            'warrantyStartsAt' => $this->warranty_starts_at?->toISOString(),
            'warrantyEndsAt' => $this->warranty_ends_at?->toISOString(),
            'warrantyAutoRenew' => (bool) $this->warranty_auto_renew,
            'isWarrantyActive' => $this->isWarrantyActive(),
            'sites' => SiteResource::collection($this->whenLoaded('sites')),
            'admin' => $this->whenLoaded('admin', fn () => $this->admin ? [
                'id' => (string) $this->admin->id,
                'name' => trim($this->admin->first_name.' '.$this->admin->last_name),
                'email' => $this->admin->email,
                'phone' => $this->admin->phone,
            ] : null),
            'employeeCount' => $this->whenCounted('employees'),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
