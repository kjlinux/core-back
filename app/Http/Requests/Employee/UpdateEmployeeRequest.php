<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['sometimes', 'exists:companies,id'],
            'site_id' => ['sometimes', 'exists:sites,id'],
            'department_id' => ['sometimes', 'exists:departments,id'],
            'first_name' => ['sometimes', 'string'],
            'last_name' => ['sometimes', 'string'],
            'email' => ['sometimes', 'email'],
            'phone' => ['sometimes', 'string'],
            'position' => ['sometimes', 'string'],
            'employee_number' => ['sometimes', 'string', Rule::unique('employees')->ignore($this->route('employee'))],
            'hire_date' => ['sometimes', 'date'],
            'avatar' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
