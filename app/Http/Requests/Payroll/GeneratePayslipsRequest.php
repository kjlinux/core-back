<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class GeneratePayslipsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id'    => ['required', 'exists:companies,id'],
            'site_id'       => ['nullable', 'exists:sites,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'period_start'  => ['required', 'date'],
            'period_end'    => ['required', 'date', 'after_or_equal:period_start'],
        ];
    }
}
