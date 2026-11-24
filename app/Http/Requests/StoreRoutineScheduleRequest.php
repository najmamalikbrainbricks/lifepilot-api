<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoutineScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recurrence_type' => ['required', 'string', 'in:one_time,daily,selected_weekdays,weekly'],
            'selected_weekdays' => ['nullable', 'array'],
            'selected_weekdays.*' => ['integer', 'between:0,6'],
            'local_start_time' => ['required', 'string'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'reminder_offset' => ['nullable', 'integer'],
            'enabled' => ['nullable', 'boolean'],
        ];
    }
}
