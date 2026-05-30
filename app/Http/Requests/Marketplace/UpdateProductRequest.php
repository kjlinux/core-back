<?php

namespace App\Http\Requests\Marketplace;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string'],
            'description' => ['sometimes', 'string'],
            'category' => ['sometimes', 'in:standard_card,custom_card,enterprise_pack'],
            'price' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:10'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0'],
            'images' => ['sometimes', 'nullable', 'array'],
            'customizable' => ['sometimes', 'boolean'],
            'min_quantity' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
