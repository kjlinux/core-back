<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class SaveLatenessRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rules'                      => ['required', 'array'],
            'rules.*.tolerance_minutes'  => ['required', 'integer', 'min:0'],
            'rules.*.minutes_threshold'  => ['required', 'integer', 'min:1'],
            'rules.*.penalty_value'      => ['required', 'numeric', 'min:0'],
            'rules.*.penalty_type'       => ['required', 'string', 'in:fixed,percentage'],
            'rules.*.apply_per'          => ['required', 'string', 'in:occurrence,tranche'],
        ];
    }
}
