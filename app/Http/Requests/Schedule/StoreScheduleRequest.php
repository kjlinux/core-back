<?php

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleRequest extends FormRequest
{
    use NormalizesScheduleDays;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeScheduleDays();
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'name' => ['required', 'string'],
            'type' => ['required', 'in:standard,custom,day,night'],
            'default_late_tolerance' => ['nullable', 'integer', 'min:0'],
            'days' => ['nullable', 'array'],
            'days.*.weekday' => ['required_with:days', 'integer', 'between:1,7'],
            'days.*.worked' => ['required_with:days', 'boolean'],
            'days.*.segments' => ['nullable', 'array'],
            'days.*.segments.*.kind' => ['required_with:days.*.segments', 'in:morning,evening,full_day,night'],
            'days.*.segments.*.startTime' => ['required_with:days.*.segments', 'string'],
            'days.*.segments.*.endTime' => ['required_with:days.*.segments', 'string'],
            'days.*.segments.*.lateTolerance' => ['nullable', 'integer', 'min:0'],
            'days.*.segments.*.expectedPunches' => ['nullable', 'array'],
            'days.*.segments.*.expectedPunches.*.time' => ['required_with:days.*.segments.*.expectedPunches', 'string'],
            'days.*.segments.*.expectedPunches.*.label' => ['nullable', 'string'],
            'assigned_departments' => ['nullable', 'array'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'break_start' => ['nullable', 'date_format:H:i'],
            'break_end' => ['nullable', 'date_format:H:i'],
            'work_days' => ['nullable', 'array'],
            'late_tolerance' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
