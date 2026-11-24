<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordPrayerCompletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prayer_name' => ['required', 'string', 'in:fajr,dhuhr,asr,maghrib,isha,tahajjud'],
            'date' => ['required', 'date'],
            'status' => ['required', 'string', 'in:completed,missed,excused'],
            'completed_at' => ['nullable', 'date'],
            'on_time' => ['nullable', 'boolean'],
            'in_jamaat' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
