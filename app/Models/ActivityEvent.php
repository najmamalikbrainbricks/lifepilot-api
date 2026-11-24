<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'task_occurrence_id',
        'event_type',
        'event_time',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'event_time' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(TaskOccurrence::class, 'task_occurrence_id');
    }
}
