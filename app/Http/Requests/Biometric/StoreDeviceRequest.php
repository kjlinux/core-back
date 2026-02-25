<?php

namespace App\Http\Requests\Biometric;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'serial_number' => ['required', 'string', 'unique:biometric_devices'],
            'company_id' => ['required', 'exists:companies,id'],
            'site_id' => ['required', 'exists:sites,id'],
            'name' => ['required', 'string'],
            'firmware_version' => ['nullable', 'string'],
        ];
    }
}
