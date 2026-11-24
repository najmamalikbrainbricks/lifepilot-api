<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrayerSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'city',
        'country',
        'latitude',
        'longitude',
        'timezone',
        'calculation_method',
        'asr_method',
        'high_latitude_rule',
        'manual_adjustments',
        'enabled_prayers',
        'tahajjud_preference',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'manual_adjustments' => 'array',
            'enabled_prayers' => 'array',
            'tahajjud_preference' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
