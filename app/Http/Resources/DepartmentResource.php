<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
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
            'siteId' => (string) $this->site_id,
            'companyId' => (string) $this->company_id,
            'name' => $this->name,
            'managerId' => (string) $this->manager_id,
            'employeeCount' => $this->whenCounted('employees'),
        ];
    }
}
