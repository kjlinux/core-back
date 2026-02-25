<?php

namespace App\Http\Requests\Biometric;

use Illuminate\Foundation\Http\FormRequest;

class StoreEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'device_id' => ['required', 'exists:biometric_devices,id'],
            'template_hash' => ['required', 'string'],
        ];
    }
}
