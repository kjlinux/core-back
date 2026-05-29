<?php

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleRequest extends FormRequest
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
            'type' => ['sometimes', 'in:standard,custom,day,night'],
            'default_late_tolerance' => ['sometimes', 'integer', 'min:0'],
            'days' => ['sometimes', 'nullable', 'array'],
            'assigned_departments' => ['sometimes', 'nullable', 'array'],
            'start_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'end_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'break_start' => ['sometimes', 'nullable', 'date_format:H:i'],
            'break_end' => ['sometimes', 'nullable', 'date_format:H:i'],
            'work_days' => ['sometimes', 'nullable', 'array'],
            'late_tolerance' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
