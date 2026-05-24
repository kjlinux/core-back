<?php

namespace App\Http\Requests\Card;

use Illuminate\Foundation\Http\FormRequest;

class BlockCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'block_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
