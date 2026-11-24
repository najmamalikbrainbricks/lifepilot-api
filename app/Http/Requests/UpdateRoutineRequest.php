<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoutineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['sometimes', 'required', 'string', 'max:50'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
            'estimated_duration' => ['nullable', 'integer', 'min:1'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:50'],
            'enabled' => ['nullable', 'boolean'],
            'archived' => ['nullable', 'boolean'],

            'steps' => ['nullable', 'array'],
            'steps.*.id' => ['nullable', 'integer'],
            'steps.*.title' => ['required_with:steps', 'string', 'max:255'],
            'steps.*.description' => ['nullable', 'string'],
            'steps.*.sort_order' => ['nullable', 'integer'],
            'steps.*.estimated_duration' => ['nullable', 'integer', 'min:1'],
            'steps.*.required' => ['nullable', 'boolean'],
            'steps.*.verification_type' => ['nullable', 'string', 'max:30'],

            'schedule' => ['nullable', 'array'],
            'schedule.recurrence_type' => ['required_with:schedule', 'string', 'in:one_time,daily,selected_weekdays,weekly'],
            'schedule.selected_weekdays' => ['nullable', 'array'],
            'schedule.selected_weekdays.*' => ['integer', 'between:0,6'],
            'schedule.local_start_time' => ['required_with:schedule', 'string'],
            'schedule.timezone' => ['nullable', 'string'],
            'schedule.start_date' => ['nullable', 'date'],
            'schedule.end_date' => ['nullable', 'date'],
            'schedule.reminder_offset' => ['nullable', 'integer'],
            'schedule.enabled' => ['nullable', 'boolean'],

            'alarm_rule' => ['nullable', 'array'],
            'alarm_rule.enabled' => ['nullable', 'boolean'],
            'alarm_rule.sound' => ['nullable', 'string'],
            'alarm_rule.repeat_interval' => ['nullable', 'integer', 'min:1'],
            'alarm_rule.max_attempts' => ['nullable', 'integer', 'min:1'],
            'alarm_rule.snooze_duration' => ['nullable', 'integer', 'min:1'],
            'alarm_rule.verification_required' => ['nullable', 'boolean'],
            'alarm_rule.escalation_preference' => ['nullable', 'string'],
        ];
    }
}
