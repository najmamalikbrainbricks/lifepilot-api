<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePrayerOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date'],
            'fajr' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'dhuhr' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'asr' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'maghrib' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'isha' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
