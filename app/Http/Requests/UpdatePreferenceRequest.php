<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'theme' => ['nullable', 'string', 'in:dark,light'],
            'time_format' => ['nullable', 'string', 'in:12h,24h'],
            'reminder_preferences' => ['nullable', 'array'],
            'sleep_goal' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'bedtime' => ['nullable', 'string', 'max:10'],
            'wake_time' => ['nullable', 'string', 'max:10'],
            'focus_goal' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'focus_category' => ['nullable', 'string', 'max:50'],
            'activity_preferences' => ['nullable', 'array'],
        ];
    }
}
