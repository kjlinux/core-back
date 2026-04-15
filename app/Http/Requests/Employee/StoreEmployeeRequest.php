<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'site_id' => ['required', 'exists:sites,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'email' => ['required', 'email'],
            'phone' => ['required', 'string'],
            'position' => ['required', 'string'],
            'employee_number' => ['required', 'string', 'unique:employees'],
            'hire_date' => ['required', 'date'],
            'avatar' => ['nullable', 'string'],
            'payment_mode' => ['nullable', 'string', 'in:monthly,hourly,daily,weekly,forfait'],
            'base_salary' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
