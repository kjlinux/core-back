<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                        => (string) $this->id,
            'companyId'                 => (string) $this->company_id,
            'defaultPaymentMode'        => $this->default_payment_mode,
            'standardDailyHours'        => $this->standard_daily_hours,
            'workingDaysPerMonth'       => $this->working_days_per_month,
            'paymentDay'                => $this->payment_day,
            'latenessDeductionEnabled'  => (bool) $this->lateness_deduction_enabled,
            'overtimeEnabled'           => (bool) $this->overtime_enabled,
            'overtimeRate'              => (float) $this->overtime_rate,
            'latenessRules'             => LatenessRuleResource::collection(
                $this->whenLoaded('latenessRules')
            ),
            'createdAt'                 => $this->created_at?->toISOString(),
            'updatedAt'                 => $this->updated_at?->toISOString(),
        ];
    }
}
