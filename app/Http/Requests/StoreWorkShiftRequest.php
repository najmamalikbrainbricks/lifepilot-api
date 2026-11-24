<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'job_name' => ['required', 'string', 'max:255'],
            'start_time' => ['required', 'string', 'max:10'],
            'end_time' => ['required', 'string', 'max:10'],
            'days_of_week' => ['required', 'array'],
            'days_of_week.*' => ['integer', 'between:0,6'],
            'fixed_or_flexible' => ['nullable', 'string', 'in:fixed,flexible'],
            'overnight' => ['nullable', 'boolean'],
            'enabled' => ['nullable', 'boolean'],
        ];
    }
}
