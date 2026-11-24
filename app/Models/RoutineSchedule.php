<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoutineSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'routine_id',
        'recurrence_type',
        'selected_weekdays',
        'local_start_time',
        'timezone',
        'start_date',
        'end_date',
        'reminder_offset',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'selected_weekdays' => 'array',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'reminder_offset' => 'integer',
            'enabled' => 'boolean',
        ];
    }

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(TaskOccurrence::class, 'schedule_id');
    }
}
