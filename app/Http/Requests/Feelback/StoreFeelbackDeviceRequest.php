<?php

namespace App\Http\Requests\Feelback;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeelbackDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'serial_number' => ['required', 'string', 'unique:feelback_devices'],
            'company_id' => ['required', 'exists:companies,id'],
            'site_id' => ['required', 'exists:sites,id'],
            'assigned_agent' => ['nullable', 'string'],
        ];
    }
}
