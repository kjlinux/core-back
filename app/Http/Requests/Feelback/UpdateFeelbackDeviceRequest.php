<?php

namespace App\Http\Requests\Feelback;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeelbackDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'serial_number' => ['sometimes', 'string', Rule::unique('feelback_devices')->ignore($this->route('feelback_device'))],
            'company_id' => ['sometimes', 'exists:companies,id'],
            'site_id' => ['sometimes', 'exists:sites,id'],
            'assigned_agent' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
