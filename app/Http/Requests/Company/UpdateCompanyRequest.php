<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email'],
            'phone' => ['sometimes', 'string'],
            'address' => ['sometimes', 'string'],
            'subscription' => ['sometimes', 'nullable', 'in:freemium,garantie,premium'],
            'logo' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
