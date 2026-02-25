<?php

namespace App\Http\Requests\Holiday;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['sometimes', 'exists:companies,id'],
            'name' => ['sometimes', 'string'],
            'date' => ['sometimes', 'date'],
            'is_recurring' => ['sometimes', 'boolean'],
        ];
    }
}
