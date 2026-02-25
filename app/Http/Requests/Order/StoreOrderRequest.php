<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.customization' => ['nullable', 'array'],
            'delivery_address' => ['required', 'array'],
            'delivery_address.full_name' => ['required', 'string'],
            'delivery_address.phone' => ['required', 'string'],
            'delivery_address.street' => ['required', 'string'],
            'delivery_address.city' => ['required', 'string'],
            'delivery_address.country' => ['required', 'string'],
            'payment_method' => ['required', 'in:mobile_money,bank_card,manual'],
        ];
    }
}
