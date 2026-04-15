<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class SavePayrollConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'default_payment_mode'       => ['sometimes', 'string', 'in:monthly,hourly,daily,weekly,forfait'],
            'standard_daily_hours'        => ['sometimes', 'integer', 'min:1', 'max:24'],
            'working_days_per_month'      => ['sometimes', 'integer', 'min:1', 'max:31'],
            'payment_day'                 => ['sometimes', 'integer', 'min:1', 'max:31'],
            'lateness_deduction_enabled'  => ['sometimes', 'boolean'],
            'overtime_enabled'            => ['sometimes', 'boolean'],
            'overtime_rate'               => ['sometimes', 'numeric', 'min:1'],
        ];
    }
}
