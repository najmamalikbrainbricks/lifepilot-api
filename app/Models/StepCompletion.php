<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StepCompletion extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_OVERRIDDEN = 'overridden';

    protected $fillable = [
        'occurrence_id',
        'routine_step_id',
        'status',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
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
