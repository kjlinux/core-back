<?php

namespace App\Http\Requests\Rfid;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRfidDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'serial_number' => ['sometimes', 'string', Rule::unique('rfid_devices')->ignore($this->route('id'))],
            'name' => ['sometimes', 'string', 'max:255'],
            'company_id' => ['sometimes', 'exists:companies,id'],
            'site_id' => ['sometimes', 'exists:sites,id'],
            'is_online' => ['sometimes', 'boolean'],
        ];
    }
}
