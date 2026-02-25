<?php

namespace App\Http\Requests\Mqtt;

use Illuminate\Foundation\Http\FormRequest;

class SendMqttCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_type' => ['required', 'in:rfid,biometric,feelback'],
            'device_id' => ['required', 'string'],
            'command' => ['required', 'in:REBOOT,RESET,STATUS,RESTART'],
        ];
    }
}
