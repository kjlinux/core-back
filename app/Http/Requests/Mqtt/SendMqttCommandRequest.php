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
        $deviceType = $this->input('device_type');
        $allowedCommands = array_keys(config("mqtt.command_codes.{$deviceType}", []));

        return [
            'device_type' => ['required', 'in:rfid,biometric'],
            'device_id' => ['required', 'string'],
            'command' => ['required', 'in:' . implode(',', $allowedCommands)],
        ];
    }
}
