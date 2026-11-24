<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderRoutineStepsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'step_ids' => ['required', 'array'],
            'step_ids.*' => ['integer', 'exists:routine_steps,id'],
        ];
    }
}
