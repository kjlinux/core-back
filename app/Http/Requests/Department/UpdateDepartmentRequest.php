<?php

namespace App\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_id' => ['sometimes', 'exists:sites,id'],
            'company_id' => ['sometimes', 'exists:companies,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'manager_id' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
