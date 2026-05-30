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
            'schedule_id' => ['nullable', 'exists:schedules,id'],
            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['required', 'string'],
            'position' => ['required', 'string'],
            'employee_number' => ['required', 'string', 'unique:employees'],
            'hire_date' => ['required', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'avatar' => ['nullable', 'string'],
            'payment_mode' => ['nullable', 'string', 'in:monthly,hourly,daily,weekly,forfait'],
            'base_salary' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Cette adresse email est déjà utilisée par un autre compte.',
            'employee_number.unique' => 'Ce matricule est déjà attribué à un autre employé.',
        ];
    }
}
