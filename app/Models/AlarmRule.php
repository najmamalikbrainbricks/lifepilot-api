<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlarmRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'routine_id',
        'enabled',
        'sound',
        'repeat_interval',
        'max_attempts',
        'snooze_duration',
        'verification_required',
        'escalation_preference',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'repeat_interval' => 'integer',
            'max_attempts' => 'integer',
            'snooze_duration' => 'integer',
            'verification_required' => 'boolean',
        ];
    }

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }
}
