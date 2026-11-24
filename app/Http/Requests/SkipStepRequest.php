<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SkipStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'override' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
