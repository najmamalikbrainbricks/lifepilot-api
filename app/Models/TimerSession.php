<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimerSession extends Model
{
    use HasFactory;

    public const STATUS_RUNNING = 'running';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'occurrence_id',
        'routine_step_id',
        'started_at',
        'paused_at',
        'completed_at',
        'elapsed_seconds',
        'status',
        'target_duration_seconds',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'paused_at' => 'datetime',
        'completed_at' => 'datetime',
        'elapsed_seconds' => 'integer',
        'target_duration_seconds' => 'integer',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(TaskOccurrence::class, 'occurrence_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(RoutineStep::class, 'routine_step_id');
    }
}
