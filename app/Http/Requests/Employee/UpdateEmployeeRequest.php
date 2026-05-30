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
            'schedule_id' => ['sometimes', 'nullable', 'exists:schedules,id'],
            'first_name' => ['sometimes', 'string'],
            'last_name' => ['sometimes', 'string'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($this->route('id'), 'employee_id')],
            'phone' => ['sometimes', 'string'],
            'position' => ['sometimes', 'string'],
            'employee_number' => ['sometimes', 'string', Rule::unique('employees')->ignore($this->route('id'))],
            'hire_date' => ['sometimes', 'date'],
            'avatar' => ['sometimes', 'nullable', 'string'],
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
