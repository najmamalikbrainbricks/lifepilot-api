<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAlarmRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['nullable', 'boolean'],
            'sound' => ['nullable', 'string', 'max:50'],
            'repeat_interval' => ['nullable', 'integer', 'min:1'],
            'max_attempts' => ['nullable', 'integer', 'min:1'],
            'snooze_duration' => ['nullable', 'integer', 'min:1'],
            'verification_required' => ['nullable', 'boolean'],
            'escalation_preference' => ['nullable', 'string', 'max:50'],
        ];
    }
}
