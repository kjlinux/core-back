<?php

namespace App\Http\Requests\Mqtt;

use Illuminate\Foundation\Http\FormRequest;

class TestMqttRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'topic' => ['required', 'string'],
        ];
    }
}
