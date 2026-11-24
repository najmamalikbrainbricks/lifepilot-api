<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskOccurrence extends Model
{
    use HasFactory;

    // States
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_DUE = 'due';
    public const STATUS_RINGING = 'ringing';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_SNOOZED = 'snoozed';
    public const STATUS_PREPARATION = 'preparation';
    public const STATUS_READY = 'ready';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_AWAITING_VERIFICATION = 'awaiting_verification';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PARTIALLY_COMPLETED = 'partially_completed';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_MISSED = 'missed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'routine_id',
        'schedule_id',
        'due_at',
        'started_at',
        'completed_at',
        'status',
        'actual_duration',
        'notes',
        'original_due_at',
        'rescheduled_at',
        'reschedule_reason',
        'is_rescheduled',
        'snoozed_until',
        'alarm_attempts',
        'ringing_started_at',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'original_due_at' => 'datetime',
            'rescheduled_at' => 'datetime',
            'snoozed_until' => 'datetime',
            'ringing_started_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'is_rescheduled' => 'boolean',
            'alarm_attempts' => 'integer',
            'actual_duration' => 'integer',
        ];
    }

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(RoutineSchedule::class, 'schedule_id');
    }

    public function stepCompletions(): HasMany
    {
        return $this->hasMany(StepCompletion::class, 'occurrence_id');
    }

    public function activityEvents(): HasMany
    {
        return $this->hasMany(ActivityEvent::class, 'task_occurrence_id');
    }

    public function evidenceSubmissions(): HasMany
    {
        return $this->hasMany(EvidenceSubmission::class, 'occurrence_id');
    }

    public function alarmEvents(): HasMany
    {
        return $this->hasMany(AlarmEvent::class, 'occurrence_id');
    }

    public function timerSessions(): HasMany
    {
        return $this->hasMany(TimerSession::class, 'occurrence_id');
    }
}
