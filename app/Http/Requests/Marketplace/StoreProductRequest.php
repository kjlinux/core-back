<?php

namespace App\Http\Requests\Marketplace;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'description' => ['required', 'string'],
            'category' => ['nullable', 'in:standard_card,custom_card,enterprise_pack'],
            'price' => ['required', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'images' => ['nullable', 'array'],
            'customizable' => ['nullable', 'boolean'],
            'min_quantity' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
