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
            'hire_date' => ['sometimes', 'date'],
            'avatar' => ['sometimes', 'nullable', 'string'],
            // Rémunération éditable à la mise à jour (était auparavant absente des
            // règles → silencieusement ignorée par validated()).
            'payment_mode' => ['sometimes', 'nullable', 'string', 'in:monthly,hourly,daily,weekly,forfait'],
            'base_salary' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Cette adresse email est déjà utilisée par un autre compte.',
        ];
    }
}
