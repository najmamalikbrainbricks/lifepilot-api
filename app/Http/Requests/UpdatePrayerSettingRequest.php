<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePrayerSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'calculation_method' => ['required', 'string', 'in:Karachi,MWL,ISNA,Egypt,Makkah,Tehran,Jafari,Gulf,Kuwait,Qatar,Singapore,France,Turkey'],
            'asr_method' => ['required', 'string', 'in:Hanafi,Standard'],
            'high_latitude_rule' => ['nullable', 'string', 'in:MiddleOfTheNight,OneSeventh,AngleBased'],
            'manual_adjustments' => ['nullable', 'array'],
            'manual_adjustments.*' => ['integer', 'between:-60,60'],
            'enabled_prayers' => ['nullable', 'array'],
            'tahajjud_preference' => ['nullable', 'array'],
            'tahajjud_preference.enabled' => ['nullable', 'boolean'],
            'tahajjud_preference.duration_minutes' => ['nullable', 'integer', 'min:5', 'max:180'],
            'tahajjud_preference.preparation_minutes' => ['nullable', 'integer', 'min:0', 'max:60'],
            'tahajjud_preference.offset_before_fajr' => ['nullable', 'integer', 'min:15', 'max:180'],
        ];
    }
}
