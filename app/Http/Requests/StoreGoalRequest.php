<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:50'],
            'target' => ['nullable', 'numeric'],
            'unit' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'integer'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
