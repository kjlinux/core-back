<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayslipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => (string) $this->id,
            'employeeId'            => (string) $this->employee_id,
            'employeeNumber'        => $this->employee?->employee_number ?? '',
            'employeeFirstName'     => $this->employee?->first_name ?? '',
            'employeeLastName'      => $this->employee?->last_name ?? '',
            'employeePosition'      => $this->employee?->position ?? '',
            'companyId'             => (string) $this->company_id,
            'companyName'           => $this->company?->name ?? '',
            'siteId'                => $this->site_id ? (string) $this->site_id : null,
            'siteName'              => $this->site?->name,
            'departmentId'          => $this->department_id ? (string) $this->department_id : null,
            'departmentName'        => $this->department?->name,
            'period'                => $this->period,
            'periodStart'           => $this->period_start?->toDateString(),
            'periodEnd'             => $this->period_end?->toDateString(),
            'paymentMode'           => $this->payment_mode,
            'baseSalary'            => $this->base_salary,
            'workedDays'            => $this->worked_days,
            'workedHours'           => $this->worked_hours,
            'absentDays'            => $this->absent_days,
            'totalLatenessMinutes'  => $this->total_lateness_minutes,
            'overtimeHours'         => $this->overtime_hours,
            'overtimeAmount'        => $this->overtime_amount,
            'latenessDeduction'     => $this->lateness_deduction,
            'absenceDeduction'      => $this->absence_deduction,
            'lines'                 => $this->lines ?? [],
            'grossAmount'           => $this->gross_amount,
            'netAmount'             => $this->net_amount,
            'status'                => $this->status,
            'generatedAt'           => $this->generated_at?->toISOString(),
        ];
    }
}
