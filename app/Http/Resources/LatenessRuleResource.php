<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LatenessRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => (string) $this->id,
            'companyId'         => (string) $this->company_id,
            'toleranceMinutes'  => $this->tolerance_minutes,
            'minutesThreshold'  => $this->minutes_threshold,
            'penaltyValue'      => $this->penalty_value,
            'penaltyType'       => $this->penalty_type,
            'applyPer'          => $this->apply_per,
            'createdAt'         => $this->created_at?->toISOString(),
            'updatedAt'         => $this->updated_at?->toISOString(),
        ];
    }
}
