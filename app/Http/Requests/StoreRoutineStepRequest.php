<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoutineStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
            'estimated_duration' => ['nullable', 'integer', 'min:1'],
            'required' => ['nullable', 'boolean'],
            'verification_type' => ['nullable', 'string', 'max:30'],
        ];
    }
}
